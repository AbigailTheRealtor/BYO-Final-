<?php

namespace App\Http\Controllers;

use App\Models\PropertyAuction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;

class ChatController extends Controller
{
    protected $httpClient;

    public function __construct()
    {
        $key = "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";
        $this->httpClient = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers' => [
                'Authorization' => 'Bearer ' .  $key,
                // 'Authorization' => 'Bearer ' . env('CHATGPT_API_KEY'),
                'Content-Type' => 'application/json',
            ],
        ]);
    }
    // Integrating View oF CHat GPT
    public function askToChatGpt_integrate_view(Request $request)
    {
        $id = $request->auction_id;
        $property_auction = PropertyAuction::whereId($id)->first();
        $owner_name=$property_auction->user->name;
        $avatar =$property_auction->user->avatar;
        $html_view=(string)view('chat', compact('id', 'owner_name', 'avatar'));
        return response()->json([
            'message'=>'200',
            'html'=>$html_view,
            'id'=>$id,
        ]);

    }
    public function askToChatGpt_integrate(Request $request)
    {
        $id = $request->id;
        $property_auction = PropertyAuction::whereId($id)->first();
        $owner_name=$property_auction->user->name;
        $meta_values = $property_auction->meta()->whereNotNull('meta_value')->pluck('meta_value', 'meta_key');
        $message = $request->question;

        // Concatenate meta values into a single message
        $meta_message = '';
        foreach ($meta_values as $meta_key => $meta_value) {
            $meta_message .= $meta_key . ': ' . $meta_value . "\n";
        }

        $messages = [
            ['role' => 'system', 'content' => 'You are'],
            ['role' => 'user', 'content' => $message],
            ['role' => 'user', 'content' => $meta_message],
        ];
        $response = $this->httpClient->post('chat/completions', [
            'json' => [
                'model' => 'gpt-3.5-turbo',
                'messages' => $messages,
                'max_tokens' => 100, // Adjust the value as needed
                'temperature' => 0.7, // Adjust the value for more focused or creative answers (0.0 - 1.0)
                'top_p' => 1, // Adjust the value for controlling the diversity of the answers (0.0 - 1.0)
                'n' => 5, // Generate multiple completions for better answer options
            ],
        ]);

        $choices = json_decode($response->getBody(), true)['choices'];
        // Retrieve the best answer from the choices
        $bestAnswer = '';
        foreach ($choices as $choice) {
            $answer = $choice['message']['content'];
            if (!empty($answer)) {
                $bestAnswer = $answer;
                break;
            }
        }

        // If no answer found, use a fallback message
        if (empty($bestAnswer)) {
            $bestAnswer = "I'm sorry, but I couldn't generate a suitable answer for your question.";
        }
        return response()->json([
            'bestAnswer'=>$bestAnswer,
            'id'=>$id,
            'message'=>200,
        ]);


    }
}
