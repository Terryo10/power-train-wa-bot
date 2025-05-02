<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TwilioWebhookController extends Controller
{
    protected $whatsAppService;
    
    /**
     * Create a new controller instance.
     *
     * @param WhatsAppService $whatsAppService
     */
    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }
    
    /**
     * Handle the incoming Twilio webhook for WhatsApp messages.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function handleWhatsAppWebhook(Request $request)
    {
        try {
            // Log the incoming request for debugging
            Log::info('Incoming WhatsApp webhook', [
                'from' => $request->input('From'),
                'to' => $request->input('To'),
                'body' => $request->input('Body'),
            ]);
            
            // Extract the WhatsApp number from the Twilio format
            $from = $request->input('From');
            $from = str_replace('whatsapp:', '', $from);
            
            // Get the message body
            $body = $request->input('Body');
            
            // Process the message
            $this->whatsAppService->handleIncomingMessage($from, $body);
            
            // Return an empty response to Twilio with 204 No Content
            return response('', 204);
        } catch (\Exception $e) {
            // Log the error
            Log::error('Error processing WhatsApp webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return an error response
            return response()->json([
                'error' => 'Failed to process the webhook',
            ], 500);
        }
    }
}