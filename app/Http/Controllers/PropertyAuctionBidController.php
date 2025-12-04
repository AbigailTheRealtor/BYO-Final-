<?php

namespace App\Http\Controllers;

use App\Mail\PropertyBidNotify;
use App\Models\PropertyAuction;
use App\Models\PropertyAuctionBid;
use App\Models\PropertyAuctionMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use \Carbon\Carbon;
use DateTime;
use Facade\Ignition\DumpRecorder\Dump;
use Illuminate\Support\Facades\Mail;

class PropertyAuctionBidController extends Controller
{
    public function add_bid($id, Request $request)
    {
        $page_data['auction'] = PropertyAuction::find($id);
        $page_data['title'] = "Add Bid for Seller's Property Auction - " . $page_data['auction']->address;
        return view('seller_property.add-bid', compact('page_data'));
    }

    public function savePABid(Request $request)
    {
        // dd($request->all());
        try {
            $data = PropertyAuction::with('meta')->find($request->auction_id);
            // if ($request->price >= $data->get->buy_now_price || $request->price >= $data->get->reserve_price) {
            $allowedPhotos = ['jpg', 'png', 'jpeg', 'gif', 'svg'];
            $allowedFiles = ['jpg', 'png', 'jpeg', 'gif', 'svg', 'csv', 'txt', 'xlx', 'xls', 'pdf', 'doc', 'docs', 'docm', 'docx', 'dot', 'dotm', 'dotx', 'odt', 'rtf', 'wps', 'xml', 'xps']; //csv,txt,xlx,xls,pdf
            $allowedVideos = ['mp4', 'mov', 'wmv', 'avi', 'mkv', 'mpeg-2'];
            $allowedAudios = ['mp3', 'wav', 'voc', 'ogg', 'oga', 'cda', 'ogv', 'm4a'];

            DB::beginTransaction();
            $bid = new PropertyAuctionBid();
            $bid->user_id = Auth::user()->id;
            $bid->property_auction_id = $request->auction_id;
            $bid->price = $request->price;
            $bid->autobid_price = $request->Price;
            $bid->autobid_price3 = $request->autobid_price3;
            $bid->inspection_period = $request->inspection_period;
            $bid->inspection_period2 = $request->inspection_period2;
            $bid->closing_days = $request->closing_days;
            $bid->closing_days2 = $request->closing_days2;
            $bid->escrow_amount = $request->escrow_amount;
            $bid->escrow_amount2 = $request->escrow_amount2;
            $bid->save();
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
            if ($request->audio != "") {
                $extension = $request->audio->getClientOriginalExtension();
                $check = in_array($extension, $allowedAudios);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $audioName = $uuid . '.' . $extension;
                    $request->audio->move(public_path('auction/bid/audios'), $audioName);
                    $bid->saveMeta('audio', 'auction/bid/audios/' . $audioName);
                }
            }
            if ($request->card != "") {
                $extension = $request->card->getClientOriginalExtension();
                $check = in_array($extension, $allowedPhotos);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $cardName = $uuid . '.' . $extension;
                    $request->card->move(public_path('auction/bid/cards'), $cardName);
                    $bid->saveMeta('card', 'auction/bid/cards/' . $cardName);
                }
            }
            if ($request->custom_exchange_picture != "") {
                $extension = $request->custom_exchange_picture->getClientOriginalExtension();
                $check = in_array($extension, $allowedPhotos);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $custom_exchange_picture = $uuid . '.' . $extension;
                    $request->custom_exchange_picture->move(public_path('auction/bid/cards'), $custom_exchange_picture);
                    $bid->saveMeta('custom_exchange_picture', 'auction/bid/cards/' . $custom_exchange_picture);
                }
            }
            if ($request->note != "") {
                $extension = $request->note->getClientOriginalExtension();
                $check = in_array($extension, $allowedFiles);
                if ($check) {
                    $uuid = (string) Str::uuid();
                    $letterName = $uuid . '.' . $extension;
                    $request->note->move(public_path('auction/bid/letters'), $letterName);
                    $bid->saveMeta('note', 'auction/bid/letters/' . $letterName);
                }
            }
            $route = route('view-pl', $request->auction_id);
            $bid_count = PropertyAuctionBid::where('property_auction_id', $request->auction_id)->count();
            $property_auction = PropertyAuction::with('meta')->find($request->auction_id);
            $date = new DateTime($property_auction->get->expiration_date); // Your initial date
            $date->modify('+1 day'); // Adding 1 day
            $date->setTime(0, 0, 0); // Setting the time to 00:00:00
            $increase_day = $date->format('Y-m-d H:i:s');
            PropertyAuctionMeta::where('meta_key', 'expiration_date')
                ->where('property_auction_id', $request->auction_id) // Adjust this condition based on your requirement
                ->update(['meta_value' => $increase_day]); // Replace $increase_day with the new value
            DB::commit();
            return redirect()->to($route)->with('success', 'Bid added successfully!');
            $route = route('view-pl', $request->auction_id);

            //     return redirect()->to($route)->with('error', 'Sorry!  "Your Offer Price does not meet our Buy Now Price OR Reserve Price.');
            // }
        } catch (\Exception $e) {
            DB::rollBack();
            return $e->getMessage();
            return redirect()->back()->with('error', 'Unable to add bid');
        }
    }
    public function acceptPABid(Request $request)
    {
        $counterPrice = $request->counterPrice;
        $pab = PropertyAuctionBid::whereId($request->bid_id)->first();
        $counterBidmax = PropertyAuctionBid::where('property_auction_id', $request->auction_id)->max('price');
        $pa = PropertyAuction::whereId($request->auction_id)->first();
        $propAuction = PropertyAuction::with('meta')->find($request->auction_id);
        if ($counterPrice >= $pa->get->starting_price && $counterPrice ?: $pa->get->starting_price >= $counterBidmax) {
            // $pab->accepted = true;
            // $pab->price = $request->counterPrice;
            // $pab->accepted_date = date('Y-m-d H:i:s');
            // Assuming $pab and $pa are instances of Eloquent models representing tables in the database.

            $pab->update(['price' => $counterPrice, 'accepted' => true, 'accepted_date' => now()]);
            $pa->update(['sold' => 1, 'is_paid' => $counterPrice, 'sold_date' => now()]);
            // $pa->sold = true;
            // $pa->is_paid = $request->price;
            // $pa->sold_date = date('Y-m-d H:i:s');
            if ($pab->update() && $pa->update()) {
                return redirect()->back()->with('success', 'Bid Accepted successfully!');
            } else {
                return redirect()->back()->with('error', 'Some problem in bid acceptance!');
            }
        } else {
            return redirect()->back()->with('error', 'Your Offered Price Should matched with Demonded price!');
        }
    }
    public function rejectPABid(Request $request)
    {

        $pab = PropertyAuctionBid::whereId($request->bid_id)->first();
        try {
            $pab->update(['accepted' => 'rejected', 'accepted_date' => date('Y-m-d H:i:s')]);
            return redirect()->back()->with('error', 'Your Bid Has Been Rejected!');
        } catch (\Exception $e) {
            dd($e->getMessage());
        }
    }
    public function destroy($id)
    {
        $counterBid = PropertyAuctionBid::findOrFail($id);
        $counterBid->delete();
        return redirect()->back()->with('success', 'Counter Bid Has Been Rejected!');
    }
}
