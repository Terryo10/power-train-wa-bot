<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class WhatsAppService
{
    protected $client;
    protected $fromNumber;
    protected $sessionTimeout = 3600; // 1 hour

    /**
     * Create a new WhatsApp service instance.
     */
    public function __construct()
    {
        $this->client = new Client(
            config('services.twilio.sid'),
            config('services.twilio.auth_token')
        );
        
        $this->fromNumber = config('services.twilio.whatsapp_number');
    }

    /**
     * Send a WhatsApp message.
     *
     * @param string $to Phone number
     * @param string $message Message content
     * @return \Twilio\Rest\Api\V2010\Account\MessageInstance
     */
    public function sendMessage($to, $message)
    {
        try {
            return $this->client->messages->create(
                "whatsapp:$to",
                [
                    'from' => "whatsapp:{$this->fromNumber}",
                    'body' => $message
                ]
            );
        } catch (\Exception $e) {
            Log::error('WhatsApp message sending failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process an incoming WhatsApp message.
     *
     * @param string $from Sender's phone number
     * @param string $body Message content
     * @return void
     */
    public function handleIncomingMessage($from, $body)
    {
        // Check if this is a driver or customer
        $driver = Driver::where('phone', $from)->first();
        
        if ($driver) {
            return $this->handleDriverMessage($driver, $body);
        }
        
        // Handle as customer message
        $sessionData = $this->getUserSession($from);
        
        switch ($sessionData['state'] ?? 'initial') {
            case 'initial':
                return $this->handleInitialState($from, $body);
            
            case 'select_product_category':
                return $this->handleCategorySelection($from, $body);
            
            case 'select_load_type':
                return $this->handleLoadTypeSelection($from, $body, $sessionData);
            
            case 'select_quantity':
                return $this->handleQuantitySelection($from, $body, $sessionData);
            
            case 'provide_delivery_address':
                return $this->handleDeliveryAddress($from, $body, $sessionData);
            
            case 'provide_contact_info':
                return $this->handleContactInfo($from, $body, $sessionData);
            
            case 'confirm_order':
                return $this->handleOrderConfirmation($from, $body, $sessionData);
            
            default:
                return $this->resetConversation($from);
        }
    }

    /**
     * Handle driver status updates.
     */
    private function handleDriverMessage(Driver $driver, $message)
    {
        $message = strtolower(trim($message));
        
        // Get the driver's current order
        $order = $driver->getCurrentOrder();
        
        if (!$order) {
            return $this->sendMessage($driver->phone, "You don't have any active orders at the moment.");
        }
        
        if (strpos($message, 'start loading') !== false) {
            $order->status = Order::STATUS_IN_PROGRESS;
            $order->save();
            
            // Notify customer
            $this->sendMessage(
                $order->customer_phone, 
                "Your order #{$order->id} is now being loaded. Your driver {$driver->name} will be on the way soon."
            );
            
            return $this->sendMessage(
                $driver->phone, 
                "You've started loading order #{$order->id}. Please send 'Start Delivery' when you're on the way."
            );
        }
        
        if (strpos($message, 'start delivery') !== false) {
            // Update status in database if needed
            
            // Notify customer
            $this->sendMessage(
                $order->customer_phone, 
                "Your driver {$driver->name} is now on the way with your order #{$order->id}."
            );
            
            return $this->sendMessage(
                $driver->phone, 
                "You've started delivery for order #{$order->id}. Please send 'Delivered' when completed."
            );
        }
        
        if (strpos($message, 'delivered') !== false) {
            $order->status = Order::STATUS_DELIVERED;
            $order->save();
            
            // Make driver available for new orders
            $driver->markAsAvailable();
            
            // Notify customer
            $this->sendMessage(
                $order->customer_phone, 
                "Your order #{$order->id} has been delivered. Thank you for choosing Powertrain!"
            );
            
            return $this->sendMessage(
                $driver->phone, 
                "Order #{$order->id} marked as delivered. Thank you!"
            );
        }
        
        // If we get here, the message wasn't recognized
        return $this->sendMessage(
            $driver->phone,
            "Please respond with one of the following:\n✔ Start Loading\n🚚 Start Delivery\n📦 Delivered"
        );
    }

    /**
     * Handle the initial welcome message.
     */
    private function handleInitialState($from, $body)
    {
        $message = "Hi 👋 Welcome to Powertrain. Please select a product category:\n";
        $message .= "1️⃣ Truck Loads (Sand/Aggregates)\n";
        $message .= "2️⃣ Building Materials (Bricks/Pavers)";
        
        $this->updateUserSession($from, [
            'state' => 'select_product_category',
            'cart' => []
        ]);
        
        return $this->sendMessage($from, $message);
    }

    /**
     * Handle the product category selection.
     */
    private function handleCategorySelection($from, $body)
    {
        $input = trim(strtolower($body));
        
        if ($input == '1' || $input == 'truck loads' || $input == 'loads') {
            // Show truck load options
            $loads = Product::loads()->get();
            
            $message = "Available truck loads:\n";
            foreach ($loads as $index => $load) {
                $message .= ($index + 1) . ". {$load->name} - \${$load->price}\n";
            }
            $message .= "\nPlease select a load type by number:";
            
            $this->updateUserSession($from, [
                'state' => 'select_load_type'
            ]);
            
            return $this->sendMessage($from, $message);
        } 
        elseif ($input == '2' || $input == 'building materials' || $input == 'materials') {
            // Show building materials categories
            $message = "Building Material Categories:\n";
            $message .= "1. Blocks & Bricks\n";
            $message .= "2. Pavers\n";
            $message .= "\nPlease select a category by number:";
            
            $this->updateUserSession($from, [
                'state' => 'select_building_material_category'
            ]);
            
            return $this->sendMessage($from, $message);
        }
        else {
            return $this->sendMessage($from, "Please select a valid option: 1 for Truck Loads or 2 for Building Materials");
        }
    }

    /**
     * Handle the load type selection.
     */
    private function handleLoadTypeSelection($from, $body, $sessionData)
    {
        $loads = Product::loads()->get();
        $selection = (int) trim($body);
        
        if ($selection > 0 && $selection <= $loads->count()) {
            $selectedLoad = $loads[$selection - 1];
            
            $message = "You selected: {$selectedLoad->name} - \${$selectedLoad->price}\n\n";
            $message .= "How many loads would you like to order?";
            
            $this->updateUserSession($from, [
                'state' => 'select_quantity',
                'selected_product' => $selectedLoad->id
            ]);
            
            return $this->sendMessage($from, $message);
        } else {
            $message = "Please select a valid load type by entering a number from 1 to {$loads->count()}:";
            return $this->sendMessage($from, $message);
        }
    }

    /**
     * Handle the quantity selection.
     */
    private function handleQuantitySelection($from, $body, $sessionData)
    {
        $quantity = (int) trim($body);
        
        if ($quantity > 0) {
            $this->updateUserSession($from, [
                'state' => 'provide_delivery_address',
                'quantity' => $quantity
            ]);
            
            $message = "Great! Please provide the delivery address:";
            return $this->sendMessage($from, $message);
        } else {
            $message = "Please enter a valid quantity (a number greater than 0):";
            return $this->sendMessage($from, $message);
        }
    }

    /**
     * Handle the delivery address.
     */
    private function handleDeliveryAddress($from, $body, $sessionData)
    {
        $address = trim($body);
        
        if (strlen($address) > 5) { // Basic validation
            $this->updateUserSession($from, [
                'state' => 'provide_contact_info',
                'delivery_address' => $address
            ]);
            
            $message = "Please provide your name:";
            return $this->sendMessage($from, $message);
        } else {
            $message = "Please provide a valid delivery address:";
            return $this->sendMessage($from, $message);
        }
    }

    /**
     * Handle the contact information.
     */
    private function handleContactInfo($from, $body, $sessionData)
    {
        $name = trim($body);
        
        if (strlen($name) > 2) { // Basic validation
            $product = Product::find($sessionData['selected_product']);
            
            $this->updateUserSession($from, [
                'state' => 'confirm_order',
                'customer_name' => $name
            ]);
            
            $message = "Order Summary:\n";
            $message .= "Product: {$product->name}\n";
            $message .= "Quantity: {$sessionData['quantity']}\n";
            $message .= "Price: \${$product->price} x {$sessionData['quantity']} = \$" . ($product->price * $sessionData['quantity']) . "\n";
            $message .= "Delivery Address: {$sessionData['delivery_address']}\n";
            $message .= "Name: {$name}\n\n";
            $message .= "To confirm your order, please reply with 'confirm'. To cancel, reply with 'cancel'.";
            
            return $this->sendMessage($from, $message);
        } else {
            $message = "Please provide your name:";
            return $this->sendMessage($from, $message);
        }
    }

    /**
     * Handle the order confirmation.
     */
    private function handleOrderConfirmation($from, $body, $sessionData)
    {
        $response = strtolower(trim($body));
        
        if ($response == 'confirm') {
            // Create the order
            $order = new Order();
            $order->customer_name = $sessionData['customer_name'];
            $order->customer_phone = $from;
            $order->delivery_address = $sessionData['delivery_address'];
            $order->status = Order::STATUS_PENDING;
            $order->save();
            
            // Add order item
            $product = Product::find($sessionData['selected_product']);
            
            $orderItem = new OrderItem();
            $orderItem->order_id = $order->id;
            $orderItem->product_id = $product->id;
            $orderItem->quantity = $sessionData['quantity'];
            $orderItem->unit_price = $product->price;
            $orderItem->save();
            
            // Clear session
            $this->clearUserSession($from);
            
            $message = "Thank you! Your order #{$order->id} has been confirmed.\n";
            $message .= "We will update you when your delivery is on the way.";
            
            return $this->sendMessage($from, $message);
        } 
        elseif ($response == 'cancel') {
            $this->clearUserSession($from);
            
            $message = "Your order has been cancelled. Thank you for considering Powertrain.";
            return $this->sendMessage($from, $message);
        } 
        else {
            $message = "Please reply with 'confirm' to place your order, or 'cancel' to cancel.";
            return $this->sendMessage($from, $message);
        }
    }

    /**
     * Reset the conversation flow.
     */
    private function resetConversation($from)
    {
        $this->clearUserSession($from);
        return $this->handleInitialState($from, '');
    }

    /**
     * Get the user session data.
     */
    private function getUserSession($phone)
    {
        return Cache::get("whatsapp_session_{$phone}", [
            'state' => 'initial',
            'cart' => []
        ]);
    }

    /**
     * Update the user session data.
     */
    private function updateUserSession($phone, $data)
    {
        $session = $this->getUserSession($phone);
        $updated = array_merge($session, $data);
        
        Cache::put("whatsapp_session_{$phone}", $updated, $this->sessionTimeout);
        
        return $updated;
    }

    /**
     * Clear the user session data.
     */
    private function clearUserSession($phone)
    {
        Cache::forget("whatsapp_session_{$phone}");
    }

    /**
     * Notify a driver about a new order assignment.
     */
    public function notifyDriver(Driver $driver, Order $order)
    {
        $message = "New delivery assignment!\n\n";
        $message .= "Order #: {$order->id}\n";
        $message .= "Delivery to: {$order->delivery_address}\n\n";
        
        // Get order details
        $itemDetails = '';
        foreach ($order->items as $item) {
            $itemDetails .= "- {$item->product->name} x {$item->quantity}\n";
        }
        
        $message .= "Products:\n{$itemDetails}\n";
        $message .= "Reply with one of the following:\n";
        $message .= "✔ Start Loading\n";
        $message .= "🚚 Start Delivery\n";
        $message .= "📦 Delivered";
        
        // Mark driver as unavailable
        $driver->markAsUnavailable();
        
        // Update order with driver ID
        $order->driver_id = $driver->id;
        $order->save();
        
        return $this->sendMessage($driver->phone, $message);
    }
}