<?php

namespace App\Http\Controllers;

use App\Models\PropertyAuction;
use App\Models\BuyerCriteriaAuction;
use App\Models\LandlordAuction;
use App\Models\TenantCriteriaAuction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AiKnowledgeController extends Controller
{
    private $listingTypeMap = [
        'seller'   => PropertyAuction::class,
        'buyer'    => BuyerCriteriaAuction::class,
        'landlord' => LandlordAuction::class,
        'tenant'   => TenantCriteriaAuction::class,
    ];

    public function show($token)
    {
        $listing = null;
        $listingType = null;

        foreach ($this->listingTypeMap as $type => $modelClass) {
            $found = $modelClass::where('ai_share_token', $token)->first();
            if ($found) {
                $listing = $found;
                $listingType = $type;
                break;
            }
        }

        if (!$listing) {
            return response()->json(['error' => 'Knowledge base not found'], 404);
        }

        $aiFaqData = $this->getAiFaqData($listing, $listingType);
        $knowledgeBase = $this->buildKnowledgeBase($aiFaqData, $listingType);
        $answeredCount = array_sum(array_map(fn($g) => count($g['answers']), $knowledgeBase));

        return response()->json([
            'listing_type'       => $listingType,
            'listing_id'         => $listing->id,
            'answered_questions' => $answeredCount,
            'knowledge_base'     => $knowledgeBase,
            'note'               => 'Only answered questions are included. Contact info, broker compensation, and agent hire data are excluded.',
        ]);
    }

    public function generateToken(Request $request)
    {
        $request->validate([
            'listing_type' => 'required|in:seller,buyer,landlord,tenant',
            'listing_id'   => 'required|integer',
        ]);

        $listing = $this->findOwnedListing($request->listing_type, $request->listing_id);

        if (!$listing) {
            return response()->json(['error' => 'Listing not found or access denied'], 403);
        }

        if (!$listing->ai_share_token) {
            $listing->ai_share_token = $this->generateUniqueToken($request->listing_type);
            $listing->save();
        }

        return response()->json([
            'token' => $listing->ai_share_token,
            'url'   => url('/ai-knowledge/' . $listing->ai_share_token),
        ]);
    }

    public function regenerateToken(Request $request)
    {
        $request->validate([
            'listing_type' => 'required|in:seller,buyer,landlord,tenant',
            'listing_id'   => 'required|integer',
        ]);

        $listing = $this->findOwnedListing($request->listing_type, $request->listing_id);

        if (!$listing) {
            return response()->json(['error' => 'Listing not found or access denied'], 403);
        }

        $listing->ai_share_token = $this->generateUniqueToken($request->listing_type);
        $listing->save();

        return response()->json([
            'token' => $listing->ai_share_token,
            'url'   => url('/ai-knowledge/' . $listing->ai_share_token),
        ]);
    }

    private function findOwnedListing($listingType, $listingId)
    {
        $authId = auth()->id();
        $modelClass = $this->listingTypeMap[$listingType];
        $listing = $modelClass::find($listingId);

        if (!$listing) return null;
        if ($listing->user_id !== $authId && !in_array(auth()->user()->user_type ?? '', ['admin'])) {
            return null;
        }

        return $listing;
    }

    private function generateUniqueToken($listingType)
    {
        $modelClass = $this->listingTypeMap[$listingType];
        do {
            $token = Str::random(48);
        } while ($modelClass::where('ai_share_token', $token)->exists());

        return $token;
    }

    private function getAiFaqData($listing, $listingType)
    {
        if ($listingType === 'tenant') {
            $raw = $listing->listing_ai_faq;
            if (is_array($raw)) return $raw;
            if (is_string($raw)) return json_decode($raw, true) ?? [];
            return [];
        }

        $raw = $listing->info('listing_ai_faq');
        if (!$raw) return [];

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function buildKnowledgeBase(array $aiFaqData, string $listingType): array
    {
        $groups = [];

        if ($listingType === 'tenant') {
            $questions = config('tenant_ai_faq.questions', []);
            $byCategory = [];
            foreach ($questions as $q) {
                $cat = $q['category'] ?? 'General';
                if (!isset($byCategory[$cat])) $byCategory[$cat] = [];
                $byCategory[$cat][$q['key']] = $q['label'];
            }
            foreach ($byCategory as $category => $items) {
                $answers = [];
                foreach ($items as $key => $label) {
                    $val = trim($aiFaqData[$key] ?? '');
                    if ($val !== '') {
                        $answers[] = ['question' => $label, 'answer' => $val];
                    }
                }
                if (!empty($answers)) {
                    $groups[] = ['category' => $category, 'answers' => $answers];
                }
            }
        } else {
            $configKey = 'ai_faq_' . $listingType;
            $questionGroups = config($configKey . '.questions', []);
            foreach ($questionGroups as $category => $questions) {
                $answers = [];
                foreach ($questions as $key => $label) {
                    $val = trim($aiFaqData[$key] ?? '');
                    if ($val !== '') {
                        $answers[] = ['question' => $label, 'answer' => $val];
                    }
                }
                if (!empty($answers)) {
                    $groups[] = ['category' => $category, 'answers' => $answers];
                }
            }
        }

        return $groups;
    }
}
