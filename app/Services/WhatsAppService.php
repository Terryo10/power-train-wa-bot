<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use App\Models\PaymentTransaction; // Add this line to import the PaymentTransaction class
use Twilio\Rest\Client;
use App\Models\User;
use GuzzleHttp\Client as HttpClient;
use App\Models\BankTransferDetail; // Import the correct BankTransferDetail class

class WhatsAppService
{
    protected $client;
    protected $httpClient;
    protected $fromNumber;
    protected $sessionTimeout = 3600; // 1 hour
    protected $accountSid;
    protected $authToken;

    // Pre-defined content SIDs for different interactive templates
    // You'll need to create these in your Twilio console first
    protected $contentSids = [
        'product_category' => 'HXb9749d5a9c43f77705d44b5f25c5ed90', // Replace with actual SIDs
        'load_selection' => 'HXa08b4b9c261b3d248118f869688b6178',
        'quantity_selection' => 'HX4c3bdf19c88fa638bd7eebb022493822',
        'order_confirmation' => 'HX4567890123abcdef4567890123abcdef',
        'building_materials' => 'HX5678901234abcdef5678901234abcdef',
        'driver_actions' => 'HX6789012345abcdef6789012345abcdef',
    ];

    /**
     * Create a new WhatsApp service instance.
     */
    public function __construct()
    {
        $this->accountSid = config('services.twilio.sid');
        $this->authToken = config('services.twilio.auth_token');

        $this->client = new Client(
            $this->accountSid,
            $this->authToken
        );

        $this->httpClient = new HttpClient([
            'base_uri' => 'https://content.twilio.com/v1/',
            'auth' => [$this->accountSid, $this->authToken]
        ]);

        $this->fromNumber = config('services.twilio.whatsapp_number');
    }

    /**
     * Send a regular WhatsApp message.
     *
     * @param string $to Phone number
     * @param string $message Message content
     * @return \Twilio\Rest\Api\V2010\Account\MessageInstance
     */
    // Modified sendMessage method in WhatsAppService.php
    public function sendMessage($to, $message)
    {
        try {
            // Log the attempt
            Log::info('Attempting to send WhatsApp message', [
                'to' => $to,
                'message_length' => strlen($message)
            ]);

            // Ensure phone has correct format (no spaces, +, etc)
            $to = preg_replace('/[^0-9]/', '', $to);

            // Make sure "whatsapp:" prefix is only added if it's not already there
            $toWithPrefix = strpos($to, 'whatsapp:') === 0 ? $to : "whatsapp:$to";
            $fromWithPrefix = strpos($this->fromNumber, 'whatsapp:') === 0 ?
                $this->fromNumber : "whatsapp:{$this->fromNumber}";

            $result = $this->client->messages->create(
                $toWithPrefix,
                [
                    'from' => $fromWithPrefix,
                    'body' => $message
                ]
            );

            // Log success
            Log::info('WhatsApp message sent successfully', [
                'to' => $to,
                'message_sid' => $result->sid
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('WhatsApp message sending failed', [
                'to' => $to,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Send an interactive message using a predefined content template.
     *
     * @param string $to Phone number
     * @param string $templateKey Key for the content template
     * @param array $variables Variables to replace in the template
     * @param string $fallbackMessage Optional fallback message text
     * @return \Twilio\Rest\Api\V2010\Account\MessageInstance
     */
    public function sendInteractiveMessage($to, $templateKey, $variables = [], $fallbackMessage = null)
    {
        try {
            // Get the content SID for the template
            $contentSid = $this->contentSids[$templateKey] ?? null;

            if (!$contentSid) {
                throw new \Exception("Content SID not found for template key: {$templateKey}");
            }

            // Prepare the message parameters
            $messageParams = [
                'from' => "whatsapp:{$this->fromNumber}",
                'contentSid' => $contentSid,
            ];

            // Add variables if present
            if (!empty($variables)) {
                $messageParams['contentVariables'] = json_encode($variables);
            }

            // Add fallback body if provided
            if ($fallbackMessage) {
                $messageParams['body'] = $fallbackMessage;
            }

            // Send the message
            return $this->client->messages->create(
                "whatsapp:$to",
                $messageParams
            );
        } catch (\Exception $e) {
            Log::error('WhatsApp interactive message failed: ' . $e->getMessage());

            // Send fallback message if interactive messaging fails
            if ($fallbackMessage) {
                return $this->sendMessage($to, $fallbackMessage);
            }

            throw $e;
        }
    }

    /**
     * Create a new interactive content template in Twilio.
     *
     * @param string $friendlyName Name for the template
     * @param array $content Content definition
     * @return string Content SID of the created template
     */
    public function createContentTemplate($friendlyName, $content)
    {
        try {
            $response = $this->httpClient->request('POST', 'Content', [
                'json' => [
                    'FriendlyName' => $friendlyName,
                    'Content' => json_encode($content)
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            return $result['sid'] ?? null;
        } catch (\Exception $e) {
            Log::error('Failed to create content template: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process an incoming WhatsApp message.
     *
     * @param string $from Sender's phone number
     * @param string $body Message content
     * @param array $interactiveData Optional interactive response data
     * @return void
     */
    public function handleIncomingMessage($from, $body, $interactiveData = null)
    {
        // Special command handling
        $body = trim(strtolower($body));

        if ($body === 'home' || $body === 'menu' || $body === 'start') {
            return $this->resetConversation($from);
        }

        if ($body === 'back') {
            return $this->handleBackNavigation($from);
        }

        // Process interactive responses if present
        if ($interactiveData) {
            $interactiveType = $interactiveData['type'] ?? null;
            $interactiveId = $interactiveData['id'] ?? null;

            if ($interactiveId) {
                // Override the body with the interactive ID
                $body = $interactiveId;
            }
        }

        // Check if this is a driver or customer
        $driver = Driver::where('phone', $from)->first();

        if ($driver) {
            return $this->handleDriverMessage($driver, $body);
        }

        // Handle as customer message
        $sessionData = $this->getUserSession($from);

        // Store previous state for back navigation
        $this->updateUserSession($from, [
            'previous_state' => $sessionData['state'] ?? 'initial'
        ]);

        switch ($sessionData['state'] ?? 'initial') {
            case 'initial':
                return $this->handleInitialState($from);

            case 'select_product_category':
                return $this->handleCategorySelection($from, $body);

            case 'select_load_type':
                return $this->handleLoadTypeSelection($from, $body, $sessionData);

            case 'select_building_material_category':
                return $this->handleBuildingMaterialCategory($from, $body, $sessionData);

            case 'select_building_material_type':
                return $this->handleBuildingMaterialType($from, $body, $sessionData);

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
     * Handle back navigation.
     */
    private function handleBackNavigation($from)
    {
        $sessionData = $this->getUserSession($from);
        $previousState = $sessionData['previous_state'] ?? 'initial';

        // Update session to previous state
        $this->updateUserSession($from, [
            'state' => $previousState
        ]);

        // Handle the previous state
        switch ($previousState) {
            case 'initial':
                return $this->handleInitialState($from);

            case 'select_product_category':
                return $this->handleInitialState($from);

            case 'select_load_type':
                return $this->handleCategorySelection($from, 'truck_loads');

            case 'select_building_material_category':
                return $this->handleCategorySelection($from, 'building_materials');

            case 'select_building_material_type':
                return $this->handleBuildingMaterialCategory($from, $sessionData['material_category'] ?? 'blocks_bricks', $sessionData);

            case 'select_quantity':
                // Determine where to go back based on product category
                $product = Product::find($sessionData['selected_product'] ?? 0);
                if ($product) {
                    if ($product->category === 'load') {
                        return $this->handleCategorySelection($from, 'truck_loads');
                    } else {
                        return $this->handleBuildingMaterialCategory($from, $sessionData['material_category'] ?? 'blocks_bricks', $sessionData);
                    }
                }
                return $this->handleInitialState($from);

            default:
                return $this->handleInitialState($from);
        }
    }

    /**
     * Handle driver status updates with interactive buttons.
     */
    private function handleDriverMessage(Driver $driver, $message)
    {
        $message = strtolower(trim($message));

        // Get the driver's current order
        $order = $driver->getCurrentOrder();

        if (!$order) {
            return $this->sendMessage($driver->phone, "You don't have any active orders at the moment.");
        }

        if ($message === 'start_loading' || strpos($message, 'start loading') !== false) {
            $order->status = Order::STATUS_IN_PROGRESS;
            $order->save();

            // Notify customer
            $this->sendMessage(
                $order->customer_phone,
                "Your order #{$order->id} is now being loaded. Your driver {$driver->name} will be on the way soon."
            );

            // Send driver next options with interactive message
            $variables = [
                '1' => $order->id, // Order ID
            ];

            $fallbackMessage = "You've started loading order #{$order->id}. Please send 'Start Delivery' when you're on the way.";

            return $this->sendInteractiveMessage(
                $driver->phone,
                'driver_actions',
                $variables,
                $fallbackMessage
            );
        }

        if ($message === 'start_delivery' || strpos($message, 'start delivery') !== false) {
            // Notify customer
            $this->sendMessage(
                $order->customer_phone,
                "Your driver {$driver->name} is now on the way with your order #{$order->id}."
            );

            // Send driver next options with interactive message
            $variables = [
                '1' => $order->id, // Order ID
            ];

            $fallbackMessage = "You've started delivery for order #{$order->id}. Please send 'Delivered' when completed.";

            return $this->sendInteractiveMessage(
                $driver->phone,
                'driver_actions',
                $variables,
                $fallbackMessage
            );
        }

        if ($message === 'delivered' || strpos($message, 'delivered') !== false) {
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
        // Send available options as interactive message
        $variables = [
            '1' => $order->id, // Order ID
        ];

        $fallbackMessage = "Please respond with one of the following:\n✔ Start Loading\n🚚 Start Delivery\n📦 Delivered";

        return $this->sendInteractiveMessage(
            $driver->phone,
            'driver_actions',
            $variables,
            $fallbackMessage
        );
    }

    /**
     * Handle the initial welcome message with interactive buttons.
     */
    private function handleInitialState($from)
    {
        $this->updateUserSession($from, [
            'state' => 'select_product_category',
            'cart' => []
        ]);

        $fallbackMessage = "Hi 👋 Welcome to Powertrain. Please select a product category:\n" .
            "1️⃣ Truck Loads (Sand/Aggregates)\n" .
            "2️⃣ Building Materials (Bricks/Pavers)";

        return $this->sendInteractiveMessage(
            $from,
            'product_category',
            [],
            $fallbackMessage
        );
    }

    /**
     * Handle the product category selection.
     */
    private function handleCategorySelection($from, $body)
    {
        $input = trim(strtolower($body));

        if ($input == '1' || $input == 'truck_loads' || $input == 'truck loads' || $input == 'loads') {
            // Show truck load options
            $loads = Product::loads()->get();

            // Create variables for load list template
            $variables = [
                '1' => $loads->count() // Number of loads
            ];

            // Add each load to the variables
            foreach ($loads as $index => $load) {
                $variables[($index + 2)] = $load->name . ' - $' . $load->price;
                $variables[($index + 2 + $loads->count())] = 'load_' . $load->id; // IDs for each option
            }

            $this->updateUserSession($from, [
                'state' => 'select_load_type'
            ]);

            $fallbackMessage = "Available truck loads:\n";
            foreach ($loads as $index => $load) {
                $fallbackMessage .= ($index + 1) . ". {$load->name} - \${$load->price}\n";
            }
            $fallbackMessage .= "\nPlease select a load type by number:";

            return $this->sendInteractiveMessage(
                $from,
                'load_selection',
                $variables,
                $fallbackMessage
            );
        } elseif ($input == '2' || $input == 'building_materials' || $input == 'building materials' || $input == 'materials') {
            // Show building materials categories
            $this->updateUserSession($from, [
                'state' => 'select_building_material_category'
            ]);

            // For building materials, we'll use a regular message with numbers
            // since we can't create interactive elements for all possible options
            $message = "Building Material Categories:\n";
            $message .= "1. Blocks & Bricks\n";
            $message .= "2. Pavers\n";
            $message .= "\nPlease select a category by number:\n";
            $message .= "Type 'home' to return to main menu or 'back' to go back.";

            return $this->sendMessage($from, $message);
        } else {
            // Invalid selection, show options again
            return $this->handleInitialState($from);
        }
    }

    /**
     * Handle the load type selection from interactive list or buttons.
     */
    private function handleLoadTypeSelection($from, $body, $sessionData)
    {
        $input = trim(strtolower($body));
        $loadId = null;

        // Check if the input is from an interactive button/list (format: load_X)
        if (strpos($input, 'load_') === 0) {
            $loadId = intval(substr($input, 5));
        } else {
            // Try to parse as a numeric selection
            $selection = intval($input);
            if ($selection > 0) {
                $loads = Product::loads()->get();
                if ($selection <= $loads->count()) {
                    $loadId = $loads[$selection - 1]->id;
                }
            }
        }

        if ($loadId) {
            $selectedLoad = Product::find($loadId);

            if ($selectedLoad) {
                $this->updateUserSession($from, [
                    'state' => 'select_quantity',
                    'selected_product' => $selectedLoad->id
                ]);

                // Create variables for quantity selection template
                $variables = [
                    '1' => $selectedLoad->name,
                    '2' => $selectedLoad->price
                ];

                $fallbackMessage = "You selected: {$selectedLoad->name} - \${$selectedLoad->price}\n\n" .
                    "How many loads would you like to order?\n" .
                    "Type a number, or type 'back' to go back.";

                return $this->sendInteractiveMessage(
                    $from,
                    'quantity_selection',
                    $variables,
                    $fallbackMessage
                );
            }
        }

        // If we get here, the selection was invalid
        // Resend the load options
        return $this->handleCategorySelection($from, 'truck_loads');
    }

    /**
     * Handle building material category selection.
     */
    private function handleBuildingMaterialCategory($from, $body, $sessionData)
    {
        $input = trim(strtolower($body));

        if ($input == 'blocks_bricks' || $input == 'blocks' || $input == 'bricks' || $input == '1') {
            // Get blocks and bricks
            $items = Product::where('category', 'building_material')
                ->whereIn('type', ['block', 'brick'])
                ->get();

            // For building materials, we'll use a regular message with numbers
            $message = "Available Blocks & Bricks:\n";
            foreach ($items as $index => $item) {
                $message .= ($index + 1) . ". {$item->name} - \${$item->price} each\n";
            }
            $message .= "\nPlease select an item by number.\n";
            $message .= "Type 'home' to return to main menu or 'back' to go back.";

            $this->updateUserSession($from, [
                'state' => 'select_building_material_type',
                'building_materials' => $items->pluck('id')->toArray(),
                'material_category' => 'blocks_bricks'
            ]);

            return $this->sendMessage($from, $message);
        } elseif ($input == 'pavers' || $input == '2') {
            // Get unique paver types
            $paverTypes = Product::where('category', 'building_material')
                ->where('type', 'paver')
                ->select('name')
                ->distinct()
                ->get()
                ->map(function ($item) {
                    // Extract the base name without variant
                    $name = $item->name;
                    if (strpos($name, ' - ') !== false) {
                        $name = substr($name, 0, strpos($name, ' - '));
                    }
                    return $name;
                })
                ->unique()
                ->values();

            // For pavers, we'll use a regular message with numbers
            $message = "Paver Types:\n";
            foreach ($paverTypes as $index => $type) {
                $message .= ($index + 1) . ". {$type}\n";
            }
            $message .= "\nPlease select a paver type by number.\n";
            $message .= "Type 'home' to return to main menu or 'back' to go back.";

            $this->updateUserSession($from, [
                'state' => 'select_building_material_type',
                'paver_types' => $paverTypes->toArray(),
                'material_category' => 'paver'
            ]);

            return $this->sendMessage($from, $message);
        } else {
            // Invalid selection, show options again
            return $this->handleCategorySelection($from, 'building_materials');
        }
    }

    /**
     * Handle building material type selection.
     */
    private function handleBuildingMaterialType($from, $body, $sessionData)
    {
        $input = trim(strtolower($body));
        $selection = intval($input);

        if ($sessionData['material_category'] === 'blocks_bricks') {
            // Selecting from blocks and bricks
            $materialIds = $sessionData['building_materials'] ?? [];

            if ($selection > 0 && $selection <= count($materialIds)) {
                $materialId = $materialIds[$selection - 1];
                $material = Product::find($materialId);

                if ($material) {
                    $this->updateUserSession($from, [
                        'state' => 'select_quantity',
                        'selected_product' => $material->id
                    ]);

                    $message = "You selected: {$material->name} - \${$material->price} each\n\n" .
                        "How many units would you like to order? (e.g. 50, 100, 200)\n" .
                        "Type 'home' to return to main menu or 'back' to go back.";

                    return $this->sendMessage($from, $message);
                }
            }
        } elseif ($sessionData['material_category'] === 'paver') {
            // Selecting from paver types
            $paverTypes = $sessionData['paver_types'] ?? [];

            if ($selection > 0 && $selection <= count($paverTypes)) {
                $paverType = $paverTypes[$selection - 1];

                // Get variants for this paver type
                $paverVariants = Product::where('category', 'building_material')
                    ->where('type', 'paver')
                    ->where('name', 'like', $paverType . '%')
                    ->get();

                // Show variants as a numbered list
                $message = "{$paverType} Paver Variants:\n";
                foreach ($paverVariants as $index => $variant) {
                    $message .= ($index + 1) . ". {$variant->name} - \${$variant->price} each\n";
                }
                $message .= "\nPlease select a variant by number.\n";
                $message .= "Type 'home' to return to main menu or 'back' to go back.";

                $this->updateUserSession($from, [
                    'state' => 'select_paver_variant',
                    'paver_variants' => $paverVariants->pluck('id')->toArray(),
                    'paver_type' => $paverType
                ]);

                return $this->sendMessage($from, $message);
            }
        } elseif ($sessionData['state'] === 'select_paver_variant') {
            // Selecting from paver variants
            $paverVariantIds = $sessionData['paver_variants'] ?? [];

            if ($selection > 0 && $selection <= count($paverVariantIds)) {
                $variantId = $paverVariantIds[$selection - 1];
                $variant = Product::find($variantId);

                if ($variant) {
                    $this->updateUserSession($from, [
                        'state' => 'select_quantity',
                        'selected_product' => $variant->id
                    ]);

                    $message = "You selected: {$variant->name} - \${$variant->price} each\n\n" .
                        "How many units would you like to order? (e.g. 50, 100, 200)\n" .
                        "Type 'home' to return to main menu or 'back' to go back.";

                    return $this->sendMessage($from, $message);
                }
            }
        }

        // If we get here, invalid selection
        // Go back to building material categories
        return $this->handleCategorySelection($from, 'building_materials');
    }

    /**
     * Handle the quantity selection.
     */
    private function handleQuantitySelection($from, $body, $sessionData)
    {
        $input = trim(strtolower($body));
        $quantity = 0;

        // Check if input is from interactive button (format: qty_X)
        if (strpos($input, 'qty_') === 0) {
            $quantity = intval(substr($input, 4));
        } else {
            // Try to parse as a numeric value
            $quantity = intval($input);
        }

        if ($quantity > 0) {
            $this->updateUserSession($from, [
                'state' => 'provide_delivery_address',
                'quantity' => $quantity
            ]);

            $message = "Great! Please provide the delivery address:\n" .
                "Type 'home' to return to main menu or 'back' to go back.";

            return $this->sendMessage($from, $message);
        } else {
            // Invalid quantity, ask again
            $product = Product::find($sessionData['selected_product']);

            $message = "Please enter a valid quantity (a number greater than 0):\n" .
                "Type 'home' to return to main menu or 'back' to go back.";

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

            $message = "Please provide your name:\n" .
                "Type 'home' to return to main menu or 'back' to go back.";

            return $this->sendMessage($from, $message);
        } else {
            $message = "Please provide a valid delivery address:\n" .
                "Type 'home' to return to main menu or 'back' to go back.";

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

            // Variables for confirmation template
            $variables = [
                '1' => $product->name,
                '2' => $sessionData['quantity'],
                '3' => $product->price,
                '4' => ($product->price * $sessionData['quantity']),
                '5' => $sessionData['delivery_address'],
                '6' => $name
            ];

            $fallbackMessage = "Order Summary:\n" .
                "Product: {$product->name}\n" .
                "Quantity: {$sessionData['quantity']}\n" .
                "Price: \${$product->price} x {$sessionData['quantity']} = \$" . ($product->price * $sessionData['quantity']) . "\n" .
                "Delivery Address: {$sessionData['delivery_address']}\n" .
                "Name: {$name}\n\n" .
                "To confirm your order, please reply with 'confirm'. To cancel, reply with 'cancel'.\n" .
                "Type 'home' to return to main menu or 'back' to go back.";

            return $this->sendInteractiveMessage(
                $from,
                'order_confirmation',
                $variables,
                $fallbackMessage
            );
        } else {
            $message = "Please provide your name:\n" .
                "Type 'home' to return to main menu or 'back' to go back.";

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

            // Send confirmation
            $message = "Thank you! Your order #{$order->id} has been confirmed.\n\n" .
                "We will update you when your delivery is on the way. " .
                "Your total amount: $" . ($product->price * $sessionData['quantity']) . "\n\n" .
                "Type 'home' to place another order.";

            return $this->sendMessage($from, $message);
        } elseif ($response == 'cancel') {
            $this->clearUserSession($from);

            // Send cancellation
            $message = "Your order has been cancelled.\n\n" .
                "Type 'home' to start over.";

            return $this->sendMessage($from, $message);
        } else {
            // Invalid response, ask again
            // Invalid response, ask again
            $product = Product::find($sessionData['selected_product']);

            $message = "Please reply with 'confirm' to place your order, or 'cancel' to cancel.\n" .
                "Type 'home' to return to main menu or 'back' to go back.";

            return $this->sendMessage($from, $message);
        }
    }

    /**
     * Reset the conversation flow.
     */
    private function resetConversation($from)
    {
        $this->clearUserSession($from);
        return $this->handleInitialState($from);
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
     * Notify a driver about a new order assignment with interactive buttons.
     */
    // Add these debug lines to app/Services/WhatsAppService.php in the notifyDriver method
    public function notifyDriver(Driver $driver, Order $order)
    {
        // Add this debug line to check if method is being called
        Log::info('Attempting to notify driver', [
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'driver_phone' => $driver->phone,
            'order_id' => $order->id
        ]);

        // Ensure phone number is in correct format (remove any formatting)
        $phone = preg_replace('/[^0-9]/', '', $driver->phone);

        // Variables for driver notification template
        $variables = [
            '1' => $order->id, // Order ID
            '2' => $order->delivery_address, // Delivery address
        ];

        // Build item details
        $itemDetails = '';
        foreach ($order->items as $item) {
            $itemDetails .= "- {$item->product->name} x {$item->quantity}\n";
        }
        $variables['3'] = $itemDetails; // Order items

        $fallbackMessage = "New delivery assignment!\n\n" .
            "Order #: {$order->id}\n" .
            "Delivery to: {$order->delivery_address}\n\n" .
            "Products:\n{$itemDetails}\n" .
            "Reply with one of the following:\n" .
            "✓ Start Loading\n" .
            "🚚 Start Delivery\n" .
            "📦 Delivered";

        // Mark driver as unavailable
        $driver->markAsUnavailable();

        // Update order with driver ID
        $order->driver_id = $driver->id;
        $order->save();

        try {
            // Try direct message if interactive message fails
            $result = $this->sendInteractiveMessage(
                $phone,
                'driver_actions',
                $variables,
                $fallbackMessage
            );

            Log::info('Driver notification sent successfully', [
                'driver_phone' => $phone,
                'message_sid' => $result->sid ?? 'unknown'
            ]);

            return $result;
        } catch (\Exception $e) {
            // Log the error and try sending a regular message instead
            Log::error('Failed to send interactive message to driver. Trying fallback.', [
                'error' => $e->getMessage(),
                'driver_phone' => $phone
            ]);

            return $this->sendMessage($phone, $fallbackMessage);
        }
    }

    // Add these functions to the WhatsAppService class

    /**
     * Handle payment-related commands from customer WhatsApp messages.
     */
    private function handlePaymentMessage($from, $body, $sessionData = null)
    {
        $body = strtoupper(trim($body));

        // Check for "PAID" message for bank transfers
        if ($body === 'PAID' || strpos($body, 'PAID') !== false) {
            // Find the customer's pending bank transfer transactions
            $customer = Order::where('customer_phone', $from)
                ->whereHas('paymentTransactions', function ($query) {
                    $query->whereHas('paymentMethod', function ($q) {
                        $q->where('code', 'bank_transfer');
                    })
                        ->whereIn('status', ['pending', 'processing']);
                })
                ->with(['paymentTransactions' => function ($query) {
                    $query->whereHas('paymentMethod', function ($q) {
                        $q->where('code', 'bank_transfer');
                    })
                        ->whereIn('status', ['pending', 'processing']);
                }])
                ->latest()
                ->first();

            if ($customer && $customer->paymentTransactions->isNotEmpty()) {
                $transaction = $customer->paymentTransactions->first();

                // Notify admin about payment confirmation
                $admins = User::where('is_admin', true)->get();
                foreach ($admins as $admin) {
                    Notification::make()
                        ->title('Bank Transfer Payment Notification')
                        ->body("Customer {$customer->customer_name} claims to have paid for Order #{$customer->id}. Please verify the payment.")
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('view')
                                ->url(route('filament.admin.resources.payment-transactions.edit', $transaction))
                        ])
                        ->sendToDatabase($admin);
                }

                // Send confirmation to customer
                return $this->sendMessage(
                    $from,
                    "Thank you for your payment notification. Our team will verify your payment for Order #{$customer->id} and update you once confirmed."
                );
            }
        }

        // Handle other payment-related commands as needed
        return false;
    }

    /**
     * Send payment request to customer.
     */
    public function sendPaymentRequest(Order $order, $message = null)
    {
        if (!$message) {
            // Get active payment methods
            $paymentMethods = PaymentMethod::active()->get();

            $message = "Your Order #{$order->id} for \${$order->getTotalAmount()} has been confirmed.\n\n";

            if ($order->payment_status !== Order::PAYMENT_STATUS_PAID) {
                $message .= "Please select a payment method:\n\n";

                foreach ($paymentMethods as $index => $method) {
                    $message .= ($index + 1) . ". {$method->name}\n";

                    if ($method->instructions) {
                        $message .= "   {$method->instructions}\n";
                    }
                }

                $message .= "\nReply with the number of your preferred payment method.";
            } else {
                $message .= "Thank you for your payment. Your order is being processed.";
            }
        }

        return $this->sendMessage($order->customer_phone, $message);
    }

    /**
     * Send payment notification to customer.
     */
    public function sendPaymentConfirmation(PaymentTransaction $transaction)
    {
        $order = $transaction->order;
        $paymentMethod = $transaction->paymentMethod;

        $message = "Your payment of \${$transaction->amount} for Order #{$order->id} via {$paymentMethod->name} has been confirmed. Thank you!";

        if ($order->status === Order::STATUS_PENDING) {
            $message .= "\n\nYour order is now being processed. We'll update you when it's ready for delivery.";
        } elseif ($order->status === Order::STATUS_IN_PROGRESS) {
            $message .= "\n\nYour order is already in progress. We'll update you when your delivery is on the way.";
        }

        return $this->sendMessage($order->customer_phone, $message);
    }

    /**
     * Ask customer to provide EcoCash phone number.
     */
    public function requestEcocashNumber(Order $order)
    {
        $message = "To pay for Order #{$order->id} with EcoCash, please reply with your EcoCash registered phone number.";
        return $this->sendMessage($order->customer_phone, $message);
    }

    /**
     * Notify about EcoCash payment initiation.
     */
    public function notifyEcocashInitiated(Order $order, $phoneNumber)
    {
        $message = "We've initiated an EcoCash payment request to {$phoneNumber} for Order #{$order->id}.\n\n";
        $message .= "Please check your phone for the payment prompt and enter your PIN to complete the transaction.";

        return $this->sendMessage($order->customer_phone, $message);
    }

    /**
     * Provide the PayPal payment link.
     */
    public function sendPaypalLink(Order $order, $paypalUrl)
    {
        $message = "To pay for Order #{$order->id} with PayPal, please use the following link:\n\n";
        $message .= $paypalUrl;

        return $this->sendMessage($order->customer_phone, $message);
    }

    /**
     * Send bank transfer instructions.
     */
    public function sendBankTransferInstructions(Order $order, BankTransferDetail $bankDetails)
    {
        $transaction = $order->paymentTransactions()
            ->whereHas('paymentMethod', function ($query) {
                $query->where('code', 'bank_transfer');
            })
            ->latest()
            ->first();

        $message = "Bank Transfer Instructions for Order #{$order->id}:\n\n";
        $message .= "Amount: \$" . ($transaction ? $transaction->amount : $order->getTotalAmount()) . "\n\n";
        $message .= "Bank: {$bankDetails->bank_name}\n";
        $message .= "Account Name: {$bankDetails->account_name}\n";
        $message .= "Account Number: {$bankDetails->account_number}\n";

        if ($bankDetails->branch_code) {
            $message .= "Branch Code: {$bankDetails->branch_code}\n";
        }

        if ($bankDetails->swift_code) {
            $message .= "SWIFT/BIC: {$bankDetails->swift_code}\n";
        }

        $message .= "\nReference: ORDER-{$order->id}\n\n";

        if ($bankDetails->instructions) {
            $message .= "Additional Instructions:\n{$bankDetails->instructions}\n\n";
        }

        $message .= "Once you've completed the transfer, please reply with 'PAID' and we'll verify your payment.";

        return $this->sendMessage($order->customer_phone, $message);
    }

    /**
     * Confirm cash on delivery arrangement.
     */
    public function confirmCashOnDelivery(Order $order)
    {
        $transaction = $order->paymentTransactions()
            ->whereHas('paymentMethod', function ($query) {
                $query->where('code', 'cod');
            })
            ->latest()
            ->first();

        $message = "Your Order #{$order->id} has been confirmed with Cash on Delivery payment option.\n\n";
        $message .= "Amount due: \$" . ($transaction ? $transaction->amount : $order->getTotalAmount()) . "\n\n";
        $message .= "Please have the exact amount ready when your delivery arrives.";

        return $this->sendMessage($order->customer_phone, $message);
    }
}
