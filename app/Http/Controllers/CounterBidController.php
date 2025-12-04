<?php

namespace App\Http\Controllers;

use App\Models\PropertyType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CounterBid;
use App\Models\CounterBidMeta;
use App\Models\PropertyAuction;
use App\Models\PropertyAuctionBid;
use App\Models\PropertyAuctionBidMeta;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class CounterBidController extends Controller
{
    public function addCounterBid(Request $request, $bid_id, $auction_id)
    {
        $page_data['auction'] = PropertyAuction::find($auction_id);
        $page_data['title'] = "Add Bid for Seller's Property Auction - " . $page_data['auction']->address;
        return view('seller_property.add-counter-bid', ['bid_id' => $bid_id, 'page_data' => $page_data]);
    }

    public function addListing(Request $request)
    {
        $page_data['title'] = 'Add Property Listing';
        $page_data['property_types'] = PropertyType::orderBy('sort', 'ASC')->get();
        return view('seller_property.add', $page_data);
    }


    public function store(Request $request, $bid_id)
    {
        $dataa = PropertyAuctionBid::with('meta')->find($bid_id);
        $bid = new PropertyAuctionBid();
        // $bidDetails = PropertyAuctionBid::where('property_auction_id', $request->auction_id)->max('price');
        $counterBidmax = PropertyAuctionBid::where('counter_id', $bid_id)->max('price');
        // if ($request->counterAmount >= $bidDetails) {
        if ($request->price >= $counterBidmax) {
            $bid->user_id = Auth::user()->id;
            $bid->counter_id = $bid_id;
            $bid->property_auction_id = $dataa->property_auction_id;
            $bid->price = $request->price;
            $bid->inspection_period = $request->inspection_period;
            $bid->autobid_price = $request->Price;
            $bid->autobid_price3 = $request->autobid_price3;
            $bid->inspection_period2 = $request->inspection_period2;
            $bid->closing_days = $request->closing_days;
            $bid->closing_days2 = $request->closing_days2;
            $bid->escrow_amount = $request->escrow_amount;
            $bid->escrow_amount2 = $request->escrow_amount2;
            $bid->save();
            $lastInsertedId = $bid->id;
            // $bid->saveMeta(
            //     "custom_seller_financings_seller_financing_amount",
            //     $dataa->get->custom_seller_financings_seller_financing_amount ?? ''
            // );
            // $bid->saveMeta("custom_seller_financings_puchase_price", $dataa->get->custom_seller_financings_puchase_price ?? '');
            // $bid->saveMeta("custom_seller_financings_down_payment", $dataa->get->custom_seller_financings_down_payment ?? '');
            // $bid->saveMeta("custom_seller_financings_interest_rate", $dataa->get->custom_seller_financings_interest_rate ?? '');
            // $bid->saveMeta("custom_seller_financings_term", $dataa->get->custom_seller_financings_term ?? '');
            // $bid->saveMeta("custom_seller_financings_monthly_payments", $dataa->get->custom_seller_financings_monthly_payments ?? '');
            // $bid->saveMeta("custom_seller_financings_balloon_payments", $dataa->get->custom_seller_financings_balloon_payments ?? '');
            // $bid->saveMeta("custom_seller_financings_security", $dataa->get->custom_seller_financings_security ?? '');
            // $bid->saveMeta("custom_seller_financings_prepayment_penalty", $dataa->get->custom_seller_financings_prepayment_penalty ?? '');
            // $bid->saveMeta("custom_seller_financings_closing_costs", $dataa->get->custom_seller_financings_closing_costs ?? '');
            // $bid->saveMeta("custom_exchange", $dataa->get->custom_exchange ?? '');
            // $bid->saveMeta("custom_exchange_offered_value", $dataa->get->custom_exchange_offered_value ?? '');
            // $bid->saveMeta("custom_exchange_offered_exchange", $dataa->get->custom_exchange_offered_exchange ?? '');
            // $bid->saveMeta("custom_exchange_offered_exchange_item", $dataa->get->custom_exchange_offered_exchange_item ?? '');
            // $bid->saveMeta("custom_exchange_price_adjustment", $dataa->get->custom_exchange_price_adjustment ?? '');
            // $bid->saveMeta("custom_exchange_price_exchange", $dataa->get->custom_exchange_price_exchange ?? '');
            // $bid->saveMeta("custom_exchange_additional", $dataa->get->custom_exchange_additional ?? '');
            // $bid->saveMeta("custom_exchange_details", $dataa->get->custom_exchange_details ?? '');
            // $bid->saveMeta("closing_days2", $dataa->get->closing_days2 ?? '');
            // $bid->saveMeta("desired_days", $dataa->get->desired_days ?? '');
            // $bid->saveMeta("carport", $dataa->get->carport ?? '');
            // $bid->saveMeta("contingency", $dataa->get->contingency ?? '');
            // $bid->saveMeta("custom_inspection", $dataa->get->custom_inspection ?? '');
            // $bid->saveMeta("custom_seller_close", $dataa->get->custom_seller_close ?? '');
            // $bid->saveMeta("custom_buyer_close", $dataa->get->custom_buyer_close ?? '');
            // $bid->saveMeta("first_name", $dataa->get->first_name ?? '');
            // $bid->saveMeta("last_name", $dataa->get->last_name ?? '');
            // $bid->saveMeta("phone_number", $dataa->get->phone_number ?? '');
            // $bid->saveMeta("email", $dataa->get->email ?? '');
            // $bid->saveMeta("buyer_id", $dataa->get->buyer_id ?? '');
            // $bid->saveMeta("proof_of_fund_url", $dataa->get->proof_of_fund_url ?? '');
            // $bid->saveMeta('price', $dataa->get->price ?? '');
            // $bid->saveMeta('financing', $dataa->get->financing ?? '');
            // $bid->saveMeta('custom_term_financings', $dataa->get->custom_term_financings ?? '');
            // $bid->saveMeta('escrow_amount', $dataa->get->escrow_amount ?? '');
            // $bid->saveMeta('closing_date', $dataa->get->closing_date ?? '');
            // $bid->saveMeta('contingencies', $dataa->get->contingencies ?? '');
            // $bid->saveMeta('custom_contingencies', $dataa->get->custom_contingencies ?? '');
            // $bid->saveMeta('seller_premium', $dataa->get->seller_premium ?? '');
            // $bid->saveMeta('buyer_premium', $dataa->get->buyer_premium ?? '');
            // $bid->saveMeta('buyer_type', $dataa->get->buyer_type ?? '');
            // $bid->saveMeta('video_url', $dataa->get->video_url ?? '');
            // $bid->saveMeta('inspection_period', $dataa->get->inspection_period ?? '');

            $bid->saveMeta('autobid_price', $request->Price);
            $bid->saveMeta('autobid_price3', $request->autobid_price3);
            $bid->saveMeta('inspection_period', $request->inspection_period);
            $bid->saveMeta('inspection_period2', $request->inspection_period2);
            $bid->saveMeta('closing_days', $request->closing_days);
            $bid->saveMeta('closing_days2', $request->closing_days2);
            $bid->saveMeta('escrow_amount', $request->escrow_amount);
            $bid->saveMeta('escrow_amount2', $request->escrow_amount2);
            $bid->saveMeta("price", $request->price);
            $bid->saveMeta("term_financings", $request->term_financings);
            $bid->saveMeta("financing", $request->financing);
            $bid->saveMeta("financeOther", $request->financeOther);
            $bid->saveMeta("purchasePrice", $request->purchasePrice);
            $bid->saveMeta("downPayment", $request->downPayment);
            $bid->saveMeta("financeAmount", $request->financeAmount);
            $bid->saveMeta("interestRate", $request->interestRate);
            $bid->saveMeta("term", $request->term);
            $bid->saveMeta("montylyPayment", $request->montylyPayment);
            $bid->saveMeta("ballonPayment", $request->ballonPayment);
            $bid->saveMeta("mortgage_approved", $request->mortgage_approved);
            $bid->saveMeta("mortgage_approved_amount", $request->mortgage_approved_amount);
            $bid->saveMeta("mortgage_denied", $request->mortgage_denied);
            $bid->saveMeta("financial_situation", $request->financial_situation);
            $bid->saveMeta("employement_duration", $request->employement_duration);
            $bid->saveMeta("employement_detail", $request->employement_detail);
            $bid->saveMeta("employement_status_change", $request->employement_status_change);
            $bid->saveMeta("gross_income", $request->gross_income);
            $bid->saveMeta("debt_payment", $request->debt_payment);
            $bid->saveMeta("credit_score", $request->credit_score);
            $bid->saveMeta("down_payment_loan_balance", $request->down_payment_loan_balance);
            $bid->saveMeta("assumable_mortgage_approved", $request->assumable_mortgage_approved);
            $bid->saveMeta("assumable_mortgage_approved_amount", $request->assumable_mortgage_approved_amount);
            $bid->saveMeta("assumable_mortgage_denied", $request->assumable_mortgage_denied);
            $bid->saveMeta("assumable_financial_situation", $request->assumable_financial_situation);
            $bid->saveMeta("assumable_employement_duration", $request->assumable_employement_duration);
            $bid->saveMeta("assumable_employement_detail", $request->assumable_employement_detail);
            $bid->saveMeta("assumable_employement_status_change", $request->assumable_employement_status_change);
            $bid->saveMeta("assumable_gross_income", $request->assumable_gross_income);
            $bid->saveMeta("assumable_debt_payment", $request->assumable_debt_payment);
            $bid->saveMeta("assumable_credit_score", $request->assumable_credit_score);

            $bid->saveMeta("custom_seller_financings_security", $request->custom_seller_financings_security);
            $bid->saveMeta("prepayment", $request->prepayment);
            $bid->saveMeta("closingCosts", $request->closingCosts);
            $bid->saveMeta("buyerOffer", $request->buyerOffer);
            $bid->saveMeta("propsValue", $request->propsValue);
            $bid->saveMeta("exchangeItem", $request->exchangeItem);
            $bid->saveMeta("exchangeValue", $request->exchangeValue);
            $bid->saveMeta("priceAdjustment", $request->priceAdjustment);
            $bid->saveMeta("exchangePrice", $request->exchangePrice);
            $bid->saveMeta("additionalTerms", $request->additionalTerms);
            $bid->saveMeta("exchangeDetails", $request->exchangeDetails);
            $bid->saveMeta("contingencies", $request->contingencies);
            $bid->saveMeta("inspection", $request->inspection);
            $bid->saveMeta("escrow_amount", $request->escrow_amount);
            $bid->saveMeta("escrow_amount2", $request->escrow_amount2);
            $bid->saveMeta("inspection_period", $request->inspection_period);
            $bid->saveMeta("inspection_period2", $request->inspection_period2);
            $bid->saveMeta("closing_days", $request->closing_days);
            $bid->saveMeta("closing_days2", $request->closing_days2);
            $bid->saveMeta("desired_days", $request->desired_days);
            $bid->saveMeta("contingency", $request->contingency);

            $bid->saveMeta("contigencies_accepted_by_seller", $request->contigencies_accepted_by_seller);
            $bid->saveMeta("inspection", $request->inspection);
            $bid->saveMeta("appraisal", $request->appraisal);
            $bid->saveMeta("finance", $request->finance);
            $bid->saveMeta("saleContingency", $request->saleContingency);
            $bid->saveMeta("acceptable", $request->acceptable);
            $bid->saveMeta("acceptable_days", $request->acceptable_days);

            $bid->saveMeta("buyer_represented", $request->buyer_represented);
            $bid->saveMeta("buyer_agent_accept_compensation", $request->buyer_agent_accept_compensation);
            $bid->saveMeta("buyer_requests_agent_commission", $request->buyer_requests_agent_commission);
            $bid->saveMeta("buyer_requests_agent_commission_amount", $request->buyer_requests_agent_commission_amount);
            $bid->saveMeta("buyer_requests_agent_commission_amount_other", $request->buyer_requests_agent_commission_amount_other);

            $bid->saveMeta("offer_expiry", $request->offer_expiry);
            $bid->saveMeta("increase_bid_price", $request->increase_bid_price);
            $bid->saveMeta("autobid_price", $request->autobid_price);
            $bid->saveMeta("autobid_escrow_deposit", $request->autobid_escrow_deposit);
            $bid->saveMeta("autobid_contingency", $request->autobid_contingency);
            $bid->saveMeta("autobid_inspection", $request->autobid_inspection);
            $bid->saveMeta("autobid_appraisal", $request->autobid_appraisal);
            $bid->saveMeta("autobid_finance", $request->autobid_finance);
            $bid->saveMeta("autobid_saleContingency", $request->autobid_saleContingency);
            $bid->saveMeta("autobid_offered_contingency", $request->autobid_offered_contingency);
            $bid->saveMeta("autobid_offered_contingency_days", $request->autobid_offered_contingency_days);
            $bid->saveMeta("autobid_closing_date", $request->autobid_closing_date);
            $bid->saveMeta("acceptable_days", $request->acceptable_days);
            $bid->saveMeta("acceptable_days", $request->acceptable_days);
            $bid->saveMeta("acceptable_days", $request->acceptable_days);
            $bid->saveMeta("acceptable_days", $request->acceptable_days);

            $bid->saveMeta("custom_inspection", $request->custom_inspection);
            $bid->saveMeta("creditForm", $request->creditForm);
            $bid->saveMeta("sellerPreminum", $request->sellerPreminum);
            $bid->saveMeta("offerCredit", $request->offerCredit);
            $bid->saveMeta("buyerPreminum", $request->buyerPreminum);
            $bid->saveMeta("first_name", $request->first_name);
            $bid->saveMeta("last_name", $request->last_name);
            $bid->saveMeta("phone_number", $request->phone_number);
            $bid->saveMeta("email", $request->email);
            $bid->saveMeta("buyer_id", $request->buyer_id);
            $bid->saveMeta("video_url", $request->video_url);
            $bid->saveMeta("proof_of_fund_url", $request->proof_of_fund_url);
            $bid->saveMeta("additional_details_counter_terms", $request->additional_details_counter_terms);
            $bid->saveMeta("offer_expiry", $request->offer_expiry);
            $bid->saveMeta("agent_commission", $request->agent_commission);

            $auction = PropertyAuction::whereId($request->auction_id)->first();
            $data = PropertyAuction::whereId($request->auction_id)->first();
            $user = User::where('id', $auction->user_id)->first();
            $counterView = PropertyAuctionBid::with('meta')->find($lastInsertedId);
            // Redirecting back with query parameters
            return redirect()->route('view-pl', ['id' => $bid_id])
            ->with([
                'success' => 'Your Counter Bid Has Been Added!',
                'data' => $data,
                'auction' => $auction,
                'user' => $user
            ]);
        } else {
            return redirect()->back()->with('error', 'Your Offered Price Should matched with Demanded price!');
        }

        // try {
        //     $serializedFormData = $data[0]->input('formData'); // Retrieve serialized data
        //     $formData = [];
        //     parse_str($serializedFormData, $formData);
        //     $counterBid = new CounterBid();
        //     $counterMeta = new CounterBidMeta();
        //     $sender = Auth::user();
        //     $receiver = User::where('id', $formData['userId'])->first();
        //     if ($formData['message'] != null) {
        //         $counterBid->sender_id = $sender->id;
        //         $counterBid->receiver_id = $formData['userId'];
        //         $counterBid->save();
        //         $counterMeta->message = $formData['message'];
        //         $counterMeta->receiver_id = $formData['userId'];
        //         $counterMeta->sender_id = $sender->id;
        //         $counterMeta->receiver = $receiver->name;
        //         $counterMeta->sender = $sender->name;
        //         $counterMeta->save();
        //         return response()->json(['redirectTo' => route('counterBiding'), 'success' => 'sdafjsla']);
        //     }
        // } catch (\Exception $e) {
        //     // dd($e);
        //     // Log the error or handle it appropriately
        //     // For example, log the error message:
        //     return response()->json(['redirectTo' => route('counterBiding'), 'error' => 'sdafjsla']);

        //     // You might want to return an error response or perform other actions based on your application's needs.
        // }
    }

    public function edit()
    {
    }

    public function update()
    {
    }

    public function list()
    {
    }


    public function viewPropertyListing()
    {
    }

    public function searchListing()
    {
    }
}
