<?php

namespace App\Http\Controllers;

use App\Models\AgentServiceAuction;
use App\Models\BotQuestion;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerCriteriaAuction;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAuction;
use App\Models\PropertyAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\TenantCriteriaAuction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BotQuestionController extends Controller
{
    public function index($type, $id, Request $request)
    {
        $page_data['title'] = 'Manage Bot Questions';
        $page_data['type'] = $type;
        $page_data['id'] = $id;
        if ($type == 'seller-property') {
            $page_data['auction'] = $auction = PropertyAuction::findOrFail($id);
            $page_data['table_title'] = "Seller's Property - " . $auction->address;
        } else if ($type == "landlord-property") {
            $page_data['auction'] = $auction = LandlordAuction::findOrFail($id);
            $page_data['table_title'] = "Landlord's Property - " . $auction->address;
        } else if ($type == "buyer-criteria") {
            $page_data['auction'] = $auction = BuyerCriteriaAuction::findOrFail($id);
            $page_data['table_title'] = "Buyer's Criteria - " . @$auction->get->property_type;
        } else if ($type == "tenant-criteria") {
            $page_data['auction'] = $auction = TenantCriteriaAuction::findOrFail($id);
            $page_data['table_title'] = "Tenant's Criteria - " . @$auction->get->property_type;
        } else if ($type == "agent-service") {
            $page_data['auction'] = $auction = AgentServiceAuction::findOrFail($id);
            $page_data['table_title'] = "Agent Service Auction - " . @$auction->get->service_type;
        } else if ($type == "buyer-agent") {
            $page_data['auction'] = $auction = BuyerAgentAuction::findOrFail($id);
            $page_data['table_title'] = "Hire Buyer's Agent - " . @$auction->title;
        } else if ($type == "seller-agent") {
            $page_data['auction'] = $auction = SellerAgentAuction::findOrFail($id);
            $page_data['table_title'] = "Hire Seller's Agent - " . @$auction->address;
        } else if ($type == "landlord-agent") {
            $page_data['auction'] = $auction = LandlordAgentAuction::findOrFail($id);
            $page_data['table_title'] = "Hire Landlord's Agent - " . @$auction->address;
        } else if ($type == "tenant-agent") {
            $page_data['auction'] = $auction = TenantAgentAuction::findOrFail($id);
            $page_data['table_title'] = "Hire Tenant's Agent - " . @$auction->title;
        } else {
            abort(404);
        }
        return view('bot_questions.index', $page_data);
    }

    public function store($type, $id, Request $request)
    {
        $bot_question = new BotQuestion();
        $bot_question->user_id = Auth::id();
        $bot_question->auction_id = $id;
        $bot_question->auction_type = $type;
        $bot_question->question = $request->question;
        $bot_question->answer = $request->answer;
        if ($bot_question->save()) {
            return redirect()->back()->with('success', 'Bot question added successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to add bot question!');
        }
    }

    public function update($id, Request $request)
    {
        $bot_question = BotQuestion::findOrFail($id);
        $bot_question->question = $request->question;
        $bot_question->answer = $request->answer;
        if ($bot_question->save()) {
            return redirect()->back()->with('success', 'Bot question updated successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to update bot question!');
        }
    }

    public function delete($id, Request $request)
    {
        $bot_question = BotQuestion::findOrFail($id);
        if ($bot_question->delete()) {
            return redirect()->back()->with('success', 'Bot question deleted successfully!');
        } else {
            return redirect()->back()->with('error', 'Unable to delete bot question!');
        }
    }
}
