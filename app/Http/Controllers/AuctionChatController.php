<?php

namespace App\Http\Controllers;

use App\Models\AgentServiceAuction;
use App\Models\AuctionChat;
use App\Models\AuctionChatToken;
use App\Models\AuctionChatUnread;
use App\Models\AuctionChatUser;
use App\Models\BotQuestion;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerCriteriaAuction;
use App\Models\CommonBotQuestion;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAuction;
use App\Models\PropertyAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\TenantCriteriaAuction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

class AuctionChatController extends Controller
{

        // New Controoler 2 June 2023
        public function new1(Request $request)
        {

            $id=$request->input('auction_id');

            $type=$request->input('type');
            $user = Auth::user();
            if ($type == 'seller-property') {
                $auction = PropertyAuction::where('id', $id)->whereHas('chat_tokens', function ($tokens) use ($user) {
                    $tokens->whereHas('chat_users', function ($chat_user) use ($user) {
                        $chat_user->where('user_id', $user->id);
                    });
                });
                if ($auction->count()) {
                    $auction = $auction->with('chat_tokens', function ($tokens) use ($user) {
                        $tokens->with('chat_users')->whereHas('chat_users', function ($chat_user) use ($user) {
                            $chat_user->where('user_id', $user->id);
                        });
                    })->first();
                    $chat_token = $auction->chat_tokens->first();
                    $route = route('messages', $chat_token->token);

                    $token12=$chat_token->token;
                        //working


                        $user = Auth::user();
                        $token_ids = [];
                        if ($user->chat_tokens) {
                            $token_ids = $user->chat_tokens->pluck('auction_chat_token_id')->toArray();
                        }


                        $chat_tokens = AuctionChatToken::where('auction_id', $id)->with(['chat_users' => function ($chat_users) use ($user) {
                            $chat_users->with('user')->where('user_id', '!=', $user->id);
                        }, 'chats'])->withCount(['chats', 'chat_users'])->orderBy('updated_at', 'DESC')->first();
                        $last_token = @$chat_tokens[0]->token12;
                        $page_data['chat_tokens'] = $chat_tokens;

                        if ($token12 != "") {
                            $page_data['token'] = $token12;
                        } else {
                            $page_data['token'] = $last_token;
                        }


                        $page_data['title'] = 'Messages';
                        $html=(string)view('messages-backup', $page_data);

                        return response()->json([
                            'message'=>200,
                            'html'=>$html,
                        ]);
                    return redirect()->to($route);


                } else {
                    // dd("other");
                    try {
                        DB::beginTransaction(); // Commit
                        $auction = PropertyAuction::findOrFail($id);
                        $chat_token = new AuctionChatToken();
                        $chat_token->auction_id = $auction->id;
                        $chat_token->auction_type = 'seller-property';
                        $chat_token->token = uniqid();
                        $chat_token->last_message = 'New Chat';
                        $chat_token->save();
                        $chat_user = new AuctionChatUser();
                        $chat_user->auction_chat_token_id = $chat_token->id;
                        $chat_user->user_id = $user->id;
                        $chat_user->save();
                        $chat_user2 = new AuctionChatUser();
                        $chat_user2->auction_chat_token_id = $chat_token->id;
                        $chat_user2->user_id = $auction->user->id;
                        $chat_user2->save();
                        DB::commit();
                        $route = route('messages', $chat_token->token);
                        dd('1');
                        return redirect()->to($route);
                    } catch (\Exception $e) {
                        //throw $e;
                        DB::rollBack();
                        return 'error';
                    }
                }
                // dd(uniqid());
            } else if ($type == 'landlord-property') {
                $auction = LandlordAuction::where('id', $id)->whereHas('chat_tokens', function ($tokens) use ($user) {
                    $tokens->whereHas('chat_users', function ($chat_user) use ($user) {
                        $chat_user->where('user_id', $user->id);
                    });
                });
                // dd($auction1->count());
                if ($auction->count()) {
                    $auction = $auction->with('chat_tokens', function ($tokens) use ($user) {
                        $tokens->with('chat_users')->whereHas('chat_users', function ($chat_user) use ($user) {
                            $chat_user->where('user_id', $user->id);
                        });
                    })->first();
                    $chat_token = $auction->chat_tokens->first();
                    $route = route('messages', $chat_token->token);
                    dd('2');
                    return redirect()->to($route);
                    // dd($chat_token->token);
                    // return 'exists';
                } else {
                    // dd("other");
                    try {
                        DB::beginTransaction(); // Commit
                        $auction = LandlordAuction::findOrFail($id);
                        $chat_token = new AuctionChatToken();
                        $chat_token->auction_id = $auction->id;
                        $chat_token->auction_type = 'landlord-property';
                        $chat_token->token = uniqid();
                        $chat_token->last_message = 'New Chat';
                        $chat_token->save();
                        $chat_user = new AuctionChatUser();
                        $chat_user->auction_chat_token_id = $chat_token->id;
                        $chat_user->user_id = $user->id;
                        $chat_user->save();
                        $chat_user2 = new AuctionChatUser();
                        $chat_user2->auction_chat_token_id = $chat_token->id;
                        $chat_user2->user_id = $auction->user->id;
                        $chat_user2->save();
                        DB::commit();
                        $route = route('messages', $chat_token->token);
                        dd('3');
                        return redirect()->to($route);
                    } catch (\Exception $e) {
                        //throw $e;
                        DB::rollBack();
                        return 'error';
                    }
                }
                // dd(uniqid());
            } else if ($type == 'buyer-criteria') {
                $auction = BuyerCriteriaAuction::where('id', $id)->whereHas('chat_tokens', function ($tokens) use ($user) {
                    $tokens->whereHas('chat_users', function ($chat_user) use ($user) {
                        $chat_user->where('user_id', $user->id);
                    });
                });
                // dd($auction1->count());
                if ($auction->count()) {
                    $auction = $auction->with('chat_tokens', function ($tokens) use ($user) {
                        $tokens->with('chat_users')->whereHas('chat_users', function ($chat_user) use ($user) {
                            $chat_user->where('user_id', $user->id);
                        });
                    })->first();
                    $chat_token = $auction->chat_tokens->first();
                    $route = route('messages', $chat_token->token);
                    dd('4');
                    return redirect()->to($route);
                    // dd($chat_token->token);
                    // return 'exists';
                } else {
                    // dd("other");
                    try {
                        DB::beginTransaction(); // Commit
                        $auction = BuyerCriteriaAuction::findOrFail($id);
                        $chat_token = new AuctionChatToken();
                        $chat_token->auction_id = $auction->id;
                        $chat_token->auction_type = 'buyer-criteria';
                        $chat_token->token = uniqid();
                        $chat_token->last_message = 'New Chat';
                        $chat_token->save();
                        $chat_user = new AuctionChatUser();
                        $chat_user->auction_chat_token_id = $chat_token->id;
                        $chat_user->user_id = $user->id;
                        $chat_user->save();
                        $chat_user2 = new AuctionChatUser();
                        $chat_user2->auction_chat_token_id = $chat_token->id;
                        $chat_user2->user_id = $auction->user->id;
                        $chat_user2->save();
                        DB::commit();
                        $route = route('messages', $chat_token->token);
                        dd('5');
                        return redirect()->to($route);
                    } catch (\Exception $e) {
                        //throw $e;
                        DB::rollBack();
                        return 'error';
                    }
                }
                // dd(uniqid());
            } else if ($type == 'tenant-criteria') {
                $auction = TenantCriteriaAuction::where('id', $id)->whereHas('chat_tokens', function ($tokens) use ($user) {
                    $tokens->whereHas('chat_users', function ($chat_user) use ($user) {
                        $chat_user->where('user_id', $user->id);
                    });
                });
                // dd($auction1->count());
                if ($auction->count()) {
                    $auction = $auction->with('chat_tokens', function ($tokens) use ($user) {
                        $tokens->with('chat_users')->whereHas('chat_users', function ($chat_user) use ($user) {
                            $chat_user->where('user_id', $user->id);
                        });
                    })->first();
                    $chat_token = $auction->chat_tokens->first();
                    $route = route('messages', $chat_token->token);
                    dd('6');
                    return redirect()->to($route);
                    // dd($chat_token->token);
                    // return 'exists';
                } else {
                    // dd("other");
                    try {
                        DB::beginTransaction(); // Commit
                        $auction = TenantCriteriaAuction::findOrFail($id);
                        $chat_token = new AuctionChatToken();
                        $chat_token->auction_id = $auction->id;
                        $chat_token->auction_type = 'tenant-criteria';
                        $chat_token->token = uniqid();
                        $chat_token->last_message = 'New Chat';
                        $chat_token->save();
                        $chat_user = new AuctionChatUser();
                        $chat_user->auction_chat_token_id = $chat_token->id;
                        $chat_user->user_id = $user->id;
                        $chat_user->save();
                        $chat_user2 = new AuctionChatUser();
                        $chat_user2->auction_chat_token_id = $chat_token->id;
                        $chat_user2->user_id = $auction->user->id;
                        $chat_user2->save();
                        DB::commit();
                        $route = route('messages', $chat_token->token);
                        dd('7');
                        return redirect()->to($route);
                    } catch (\Exception $e) {
                        //throw $e;
                        DB::rollBack();
                        return 'error';
                    }
                }
                // dd(uniqid());
            } else if ($type == 'buyer-agent') {
                $auction = BuyerAgentAuction::where('id', $id)->whereHas('chat_tokens', function ($tokens) use ($user) {
                    $tokens->whereHas('chat_users', function ($chat_user) use ($user) {
                        $chat_user->where('user_id', $user->id);
                    });
                });
                // dd($auction1->count());
                if ($auction->count()) {
                    $auction = $auction->with('chat_tokens', function ($tokens) use ($user) {
                        $tokens->with('chat_users')->whereHas('chat_users', function ($chat_user) use ($user) {
                            $chat_user->where('user_id', $user->id);
                        });
                    })->first();
                    $chat_token = $auction->chat_tokens->first();
                    $route = route('messages', $chat_token->token);
                    dd('8');
                    return redirect()->to($route);
                    // dd($chat_token->token);
                    // return 'exists';
                } else {
                    // dd("other");
                    try {
                        DB::beginTransaction(); // Commit
                        $auction = BuyerAgentAuction::findOrFail($id);
                        $chat_token = new AuctionChatToken();
                        $chat_token->auction_id = $auction->id;
                        $chat_token->auction_type = 'buyer-agent';
                        $chat_token->token = uniqid();
                        $chat_token->last_message = 'New Chat';
                        $chat_token->save();
                        $chat_user = new AuctionChatUser();
                        $chat_user->auction_chat_token_id = $chat_token->id;
                        $chat_user->user_id = $user->id;
                        $chat_user->save();
                        $chat_user2 = new AuctionChatUser();
                        $chat_user2->auction_chat_token_id = $chat_token->id;
                        $chat_user2->user_id = $auction->user->id;
                        $chat_user2->save();
                        DB::commit();
                        $route = route('messages', $chat_token->token);
                        dd('9');
                        return redirect()->to($route);
                    } catch (\Exception $e) {
                        //throw $e;
                        DB::rollBack();
                        return 'error';
                    }
                }
                // dd(uniqid());
            } else if ($type == 'seller-agent') {
                $auction = SellerAgentAuction::where('id', $id)->whereHas('chat_tokens', function ($tokens) use ($user) {
                    $tokens->whereHas('chat_users', function ($chat_user) use ($user) {
                        $chat_user->where('user_id', $user->id);
                    });
                });
                // dd($auction1->count());
                if ($auction->count()) {
                    $auction = $auction->with('chat_tokens', function ($tokens) use ($user) {
                        $tokens->with('chat_users')->whereHas('chat_users', function ($chat_user) use ($user) {
                            $chat_user->where('user_id', $user->id);
                        });
                    })->first();
                    $chat_token = $auction->chat_tokens->first();
                    $route = route('messages', $chat_token->token);
                    dd('10');
                    return redirect()->to($route);
                    // dd($chat_token->token);
                    // return 'exists';
                } else {
                    // dd("other");
                    try {
                        DB::beginTransaction(); // Commit
                        $auction = SellerAgentAuction::findOrFail($id);
                        $chat_token = new AuctionChatToken();
                        $chat_token->auction_id = $auction->id;
                        $chat_token->auction_type = 'seller-agent';
                        $chat_token->token = uniqid();
                        $chat_token->last_message = 'New Chat';
                        $chat_token->save();
                        $chat_user = new AuctionChatUser();
                        $chat_user->auction_chat_token_id = $chat_token->id;
                        $chat_user->user_id = $user->id;
                        $chat_user->save();
                        $chat_user2 = new AuctionChatUser();
                        $chat_user2->auction_chat_token_id = $chat_token->id;
                        $chat_user2->user_id = $auction->user->id;
                        $chat_user2->save();
                        DB::commit();
                        $route = route('messages', $chat_token->token);
                        dd('11');
                        return redirect()->to($route);
                    } catch (\Exception $e) {
                        //throw $e;
                        DB::rollBack();
                        return 'error';
                    }
                }
                // dd(uniqid());
            } else if ($type == 'landlord-agent') {
                $auction = LandlordAgentAuction::where('id', $id)->whereHas('chat_tokens', function ($tokens) use ($user) {
                    $tokens->whereHas('chat_users', function ($chat_user) use ($user) {
                        $chat_user->where('user_id', $user->id);
                    });
                });
                // dd($auction1->count());
                if ($auction->count()) {
                    $auction = $auction->with('chat_tokens', function ($tokens) use ($user) {
                        $tokens->with('chat_users')->whereHas('chat_users', function ($chat_user) use ($user) {
                            $chat_user->where('user_id', $user->id);
                        });
                    })->first();
                    $chat_token = $auction->chat_tokens->first();
                    $route = route('messages', $chat_token->token);
                    dd('12');
                    return redirect()->to($route);
                    // dd($chat_token->token);
                    // return 'exists';
                } else {
                    // dd("other");
                    try {
                        DB::beginTransaction(); // Commit
                        $auction = LandlordAgentAuction::findOrFail($id);
                        $chat_token = new AuctionChatToken();
                        $chat_token->auction_id = $auction->id;
                        $chat_token->auction_type = 'landlord-agent';
                        $chat_token->token = uniqid();
                        $chat_token->last_message = 'New Chat';
                        $chat_token->save();
                        $chat_user = new AuctionChatUser();
                        $chat_user->auction_chat_token_id = $chat_token->id;
                        $chat_user->user_id = $user->id;
                        $chat_user->save();
                        $chat_user2 = new AuctionChatUser();
                        $chat_user2->auction_chat_token_id = $chat_token->id;
                        $chat_user2->user_id = $auction->user->id;
                        $chat_user2->save();
                        DB::commit();
                        $route = route('messages', $chat_token->token);
                        dd('13');
                        return redirect()->to($route);
                    } catch (\Exception $e) {
                        //throw $e;
                        DB::rollBack();
                        return 'error';
                    }
                }
                // dd(uniqid());
            } else if ($type == 'tenant-agent') {
                $auction = TenantAgentAuction::where('id', $id)->whereHas('chat_tokens', function ($tokens) use ($user) {
                    $tokens->whereHas('chat_users', function ($chat_user) use ($user) {
                        $chat_user->where('user_id', $user->id);
                    });
                });
                // dd($auction1->count());
                if ($auction->count()) {
                    $auction = $auction->with('chat_tokens', function ($tokens) use ($user) {
                        $tokens->with('chat_users')->whereHas('chat_users', function ($chat_user) use ($user) {
                            $chat_user->where('user_id', $user->id);
                        });
                    })->first();
                    $chat_token = $auction->chat_tokens->first();
                    $route = route('messages', $chat_token->token);
                    dd('14');
                    return redirect()->to($route);
                    // dd($chat_token->token);
                    // return 'exists';
                } else {
                    // dd("other");
                    try {
                        DB::beginTransaction(); // Commit
                        $auction = TenantAgentAuction::findOrFail($id);
                        $chat_token = new AuctionChatToken();
                        $chat_token->auction_id = $auction->id;
                        $chat_token->auction_type = 'tenant-agent';
                        $chat_token->token = uniqid();
                        $chat_token->last_message = 'New Chat';
                        $chat_token->save();
                        $chat_user = new AuctionChatUser();
                        $chat_user->auction_chat_token_id = $chat_token->id;
                        $chat_user->user_id = $user->id;
                        $chat_user->save();
                        $chat_user2 = new AuctionChatUser();
                        $chat_user2->auction_chat_token_id = $chat_token->id;
                        $chat_user2->user_id = $auction->user->id;
                        $chat_user2->save();
                        DB::commit();
                        $route = route('messages', $chat_token->token);
                        dd('15');
                        return redirect()->to($route);
                    } catch (\Exception $e) {
                        //throw $e;
                        DB::rollBack();
                        return 'error';
                    }
                }
                // dd(uniqid());
            } else if ($type == 'agent-service') {
                $auction = AgentServiceAuction::where('id', $id)->whereHas('chat_tokens', function ($tokens) use ($user) {
                    $tokens->whereHas('chat_users', function ($chat_user) use ($user) {
                        $chat_user->where('user_id', $user->id);
                    });
                });
                // dd($auction1->count());
                if ($auction->count()) {
                    $auction = $auction->with('chat_tokens', function ($tokens) use ($user) {
                        $tokens->with('chat_users')->whereHas('chat_users', function ($chat_user) use ($user) {
                            $chat_user->where('user_id', $user->id);
                        });
                    })->first();
                    $chat_token = $auction->chat_tokens->first();
                    $route = route('messages', $chat_token->token);
                    dd('16');
                    return redirect()->to($route);
                    // dd($chat_token->token);
                    // return 'exists';
                } else {
                    // dd("other");
                    try {
                        DB::beginTransaction(); // Commit
                        $auction = AgentServiceAuction::findOrFail($id);
                        $chat_token = new AuctionChatToken();
                        $chat_token->auction_id = $auction->id;
                        $chat_token->auction_type = 'agent-service';
                        $chat_token->token = uniqid();
                        $chat_token->last_message = 'New Chat';
                        $chat_token->save();
                        $chat_user = new AuctionChatUser();
                        $chat_user->auction_chat_token_id = $chat_token->id;
                        $chat_user->user_id = $user->id;
                        $chat_user->save();
                        $chat_user2 = new AuctionChatUser();
                        $chat_user2->auction_chat_token_id = $chat_token->id;
                        $chat_user2->user_id = $auction->user->id;
                        $chat_user2->save();
                        DB::commit();
                        $route = route('messages', $chat_token->token);
                        dd('17');
                        return redirect()->to($route);
                    } catch (\Exception $e) {
                        //throw $e;
                        DB::rollBack();
                        return 'error';
                    }
                }
                dd(uniqid());
            }



        }

        // New Controller 2 June 2023

    public function new($type, $id, Request $request)
    {
        $user = Auth::user();
        // dd($user->chat_tokens->toArray());
        if ($type == 'seller-property') {
            $auction = PropertyAuction::where('id', $id)->whereHas('chat_tokens', function ($tokens) use ($user) {
                $tokens->whereHas('chat_users', function ($chat_user) use ($user) {
                    $chat_user->where('user_id', $user->id);
                });
            });
            // dd($auction1->count());
            if ($auction->count()) {
                $auction = $auction->with('chat_tokens', function ($tokens) use ($user) {
                    $tokens->with('chat_users')->whereHas('chat_users', function ($chat_user) use ($user) {
                        $chat_user->where('user_id', $user->id);
                    });
                })->first();
                $chat_token = $auction->chat_tokens->first();
                $route = route('messages', $chat_token->token);
                return redirect()->to($route);
                // dd($chat_token->token);
                // return 'exists';
            } else {
                // dd("other");
                try {
                    DB::beginTransaction(); // Commit
                    $auction = PropertyAuction::findOrFail($id);
                    $chat_token = new AuctionChatToken();
                    $chat_token->auction_id = $auction->id;
                    $chat_token->auction_type = 'seller-property';
                    $chat_token->token = uniqid();
                    $chat_token->last_message = 'New Chat';
                    $chat_token->save();
                    $chat_user = new AuctionChatUser();
                    $chat_user->auction_chat_token_id = $chat_token->id;
                    $chat_user->user_id = $user->id;
                    $chat_user->save();
                    $chat_user2 = new AuctionChatUser();
                    $chat_user2->auction_chat_token_id = $chat_token->id;
                    $chat_user2->user_id = $auction->user->id;
                    $chat_user2->save();
                    DB::commit();
                    $route = route('messages', $chat_token->token);
                    return redirect()->to($route);
                } catch (\Exception $e) {
                    //throw $e;
                    DB::rollBack();
                    return 'error';
                }
            }
            // dd(uniqid());
        } else if ($type == 'landlord-property') {
            $auction = LandlordAuction::where('id', $id)->whereHas('chat_tokens', function ($tokens) use ($user) {
                $tokens->whereHas('chat_users', function ($chat_user) use ($user) {
                    $chat_user->where('user_id', $user->id);
                });
            });
            // dd($auction1->count());
            if ($auction->count()) {
                $auction = $auction->with('chat_tokens', function ($tokens) use ($user) {
                    $tokens->with('chat_users')->whereHas('chat_users', function ($chat_user) use ($user) {
                        $chat_user->where('user_id', $user->id);
                    });
                })->first();
                $chat_token = $auction->chat_tokens->first();
                $route = route('messages', $chat_token->token);
                return redirect()->to($route);
                // dd($chat_token->token);
                // return 'exists';
            } else {
                // dd("other");
                try {
                    DB::beginTransaction(); // Commit
                    $auction = LandlordAuction::findOrFail($id);
                    $chat_token = new AuctionChatToken();
                    $chat_token->auction_id = $auction->id;
                    $chat_token->auction_type = 'landlord-property';
                    $chat_token->token = uniqid();
                    $chat_token->last_message = 'New Chat';
                    $chat_token->save();
                    $chat_user = new AuctionChatUser();
                    $chat_user->auction_chat_token_id = $chat_token->id;
                    $chat_user->user_id = $user->id;
                    $chat_user->save();
                    $chat_user2 = new AuctionChatUser();
                    $chat_user2->auction_chat_token_id = $chat_token->id;
                    $chat_user2->user_id = $auction->user->id;
                    $chat_user2->save();
                    DB::commit();
                    $route = route('messages', $chat_token->token);
                    return redirect()->to($route);
                } catch (\Exception $e) {
                    //throw $e;
                    DB::rollBack();
                    return 'error';
                }
            }
            // dd(uniqid());
        } else if ($type == 'buyer-criteria') {
            $auction = BuyerCriteriaAuction::where('id', $id)->whereHas('chat_tokens', function ($tokens) use ($user) {
                $tokens->whereHas('chat_users', function ($chat_user) use ($user) {
                    $chat_user->where('user_id', $user->id);
                });
            });
            // dd($auction1->count());
            if ($auction->count()) {
                $auction = $auction->with('chat_tokens', function ($tokens) use ($user) {
                    $tokens->with('chat_users')->whereHas('chat_users', function ($chat_user) use ($user) {
                        $chat_user->where('user_id', $user->id);
                    });
                })->first();
                $chat_token = $auction->chat_tokens->first();
                $route = route('messages', $chat_token->token);
                return redirect()->to($route);
                // dd($chat_token->token);
                // return 'exists';
            } else {
                // dd("other");
                try {
                    DB::beginTransaction(); // Commit
                    $auction = BuyerCriteriaAuction::findOrFail($id);
                    $chat_token = new AuctionChatToken();
                    $chat_token->auction_id = $auction->id;
                    $chat_token->auction_type = 'buyer-criteria';
                    $chat_token->token = uniqid();
                    $chat_token->last_message = 'New Chat';
                    $chat_token->save();
                    $chat_user = new AuctionChatUser();
                    $chat_user->auction_chat_token_id = $chat_token->id;
                    $chat_user->user_id = $user->id;
                    $chat_user->save();
                    $chat_user2 = new AuctionChatUser();
                    $chat_user2->auction_chat_token_id = $chat_token->id;
                    $chat_user2->user_id = $auction->user->id;
                    $chat_user2->save();
                    DB::commit();
                    $route = route('messages', $chat_token->token);
                    return redirect()->to($route);
                } catch (\Exception $e) {
                    //throw $e;
                    DB::rollBack();
                    return 'error';
                }
            }
            // dd(uniqid());
        } else if ($type == 'tenant-criteria') {
            $auction = TenantCriteriaAuction::where('id', $id)->whereHas('chat_tokens', function ($tokens) use ($user) {
                $tokens->whereHas('chat_users', function ($chat_user) use ($user) {
                    $chat_user->where('user_id', $user->id);
                });
            });
            // dd($auction1->count());
            if ($auction->count()) {
                $auction = $auction->with('chat_tokens', function ($tokens) use ($user) {
                    $tokens->with('chat_users')->whereHas('chat_users', function ($chat_user) use ($user) {
                        $chat_user->where('user_id', $user->id);
                    });
                })->first();
                $chat_token = $auction->chat_tokens->first();
                $route = route('messages', $chat_token->token);
                return redirect()->to($route);
                // dd($chat_token->token);
                // return 'exists';
            } else {
                // dd("other");
                try {
                    DB::beginTransaction(); // Commit
                    $auction = TenantCriteriaAuction::findOrFail($id);
                    $chat_token = new AuctionChatToken();
                    $chat_token->auction_id = $auction->id;
                    $chat_token->auction_type = 'tenant-criteria';
                    $chat_token->token = uniqid();
                    $chat_token->last_message = 'New Chat';
                    $chat_token->save();
                    $chat_user = new AuctionChatUser();
                    $chat_user->auction_chat_token_id = $chat_token->id;
                    $chat_user->user_id = $user->id;
                    $chat_user->save();
                    $chat_user2 = new AuctionChatUser();
                    $chat_user2->auction_chat_token_id = $chat_token->id;
                    $chat_user2->user_id = $auction->user->id;
                    $chat_user2->save();
                    DB::commit();
                    $route = route('messages', $chat_token->token);
                    return redirect()->to($route);
                } catch (\Exception $e) {
                    //throw $e;
                    DB::rollBack();
                    return 'error';
                }
            }
            // dd(uniqid());
        } else if ($type == 'buyer-agent') {
            $auction = BuyerAgentAuction::where('id', $id)->whereHas('chat_tokens', function ($tokens) use ($user) {
                $tokens->whereHas('chat_users', function ($chat_user) use ($user) {
                    $chat_user->where('user_id', $user->id);
                });
            });
            // dd($auction1->count());
            if ($auction->count()) {
                $auction = $auction->with('chat_tokens', function ($tokens) use ($user) {
                    $tokens->with('chat_users')->whereHas('chat_users', function ($chat_user) use ($user) {
                        $chat_user->where('user_id', $user->id);
                    });
                })->first();
                $chat_token = $auction->chat_tokens->first();
                $route = route('messages', $chat_token->token);
                return redirect()->to($route);
                // dd($chat_token->token);
                // return 'exists';
            } else {
                // dd("other");
                try {
                    DB::beginTransaction(); // Commit
                    $auction = BuyerAgentAuction::findOrFail($id);
                    $chat_token = new AuctionChatToken();
                    $chat_token->auction_id = $auction->id;
                    $chat_token->auction_type = 'buyer-agent';
                    $chat_token->token = uniqid();
                    $chat_token->last_message = 'New Chat';
                    $chat_token->save();
                    $chat_user = new AuctionChatUser();
                    $chat_user->auction_chat_token_id = $chat_token->id;
                    $chat_user->user_id = $user->id;
                    $chat_user->save();
                    $chat_user2 = new AuctionChatUser();
                    $chat_user2->auction_chat_token_id = $chat_token->id;
                    $chat_user2->user_id = $auction->user->id;
                    $chat_user2->save();
                    DB::commit();
                    $route = route('messages', $chat_token->token);
                    return redirect()->to($route);
                } catch (\Exception $e) {
                    //throw $e;
                    DB::rollBack();
                    return 'error';
                }
            }
            // dd(uniqid());
        } else if ($type == 'seller-agent') {
            $auction = SellerAgentAuction::where('id', $id)->whereHas('chat_tokens', function ($tokens) use ($user) {
                $tokens->whereHas('chat_users', function ($chat_user) use ($user) {
                    $chat_user->where('user_id', $user->id);
                });
            });
            // dd($auction1->count());
            if ($auction->count()) {
                $auction = $auction->with('chat_tokens', function ($tokens) use ($user) {
                    $tokens->with('chat_users')->whereHas('chat_users', function ($chat_user) use ($user) {
                        $chat_user->where('user_id', $user->id);
                    });
                })->first();
                $chat_token = $auction->chat_tokens->first();
                $route = route('messages', $chat_token->token);
                return redirect()->to($route);
                // dd($chat_token->token);
                // return 'exists';
            } else {
                // dd("other");
                try {
                    DB::beginTransaction(); // Commit
                    $auction = SellerAgentAuction::findOrFail($id);
                    $chat_token = new AuctionChatToken();
                    $chat_token->auction_id = $auction->id;
                    $chat_token->auction_type = 'seller-agent';
                    $chat_token->token = uniqid();
                    $chat_token->last_message = 'New Chat';
                    $chat_token->save();
                    $chat_user = new AuctionChatUser();
                    $chat_user->auction_chat_token_id = $chat_token->id;
                    $chat_user->user_id = $user->id;
                    $chat_user->save();
                    $chat_user2 = new AuctionChatUser();
                    $chat_user2->auction_chat_token_id = $chat_token->id;
                    $chat_user2->user_id = $auction->user->id;
                    $chat_user2->save();
                    DB::commit();
                    $route = route('messages', $chat_token->token);
                    return redirect()->to($route);
                } catch (\Exception $e) {
                    //throw $e;
                    DB::rollBack();
                    return 'error';
                }
            }
            // dd(uniqid());
        } else if ($type == 'landlord-agent') {
            $auction = LandlordAgentAuction::where('id', $id)->whereHas('chat_tokens', function ($tokens) use ($user) {
                $tokens->whereHas('chat_users', function ($chat_user) use ($user) {
                    $chat_user->where('user_id', $user->id);
                });
            });
            // dd($auction1->count());
            if ($auction->count()) {
                $auction = $auction->with('chat_tokens', function ($tokens) use ($user) {
                    $tokens->with('chat_users')->whereHas('chat_users', function ($chat_user) use ($user) {
                        $chat_user->where('user_id', $user->id);
                    });
                })->first();
                $chat_token = $auction->chat_tokens->first();
                $route = route('messages', $chat_token->token);
                return redirect()->to($route);
                // dd($chat_token->token);
                // return 'exists';
            } else {
                // dd("other");
                try {
                    DB::beginTransaction(); // Commit
                    $auction = LandlordAgentAuction::findOrFail($id);
                    $chat_token = new AuctionChatToken();
                    $chat_token->auction_id = $auction->id;
                    $chat_token->auction_type = 'landlord-agent';
                    $chat_token->token = uniqid();
                    $chat_token->last_message = 'New Chat';
                    $chat_token->save();
                    $chat_user = new AuctionChatUser();
                    $chat_user->auction_chat_token_id = $chat_token->id;
                    $chat_user->user_id = $user->id;
                    $chat_user->save();
                    $chat_user2 = new AuctionChatUser();
                    $chat_user2->auction_chat_token_id = $chat_token->id;
                    $chat_user2->user_id = $auction->user->id;
                    $chat_user2->save();
                    DB::commit();
                    $route = route('messages', $chat_token->token);
                    return redirect()->to($route);
                } catch (\Exception $e) {
                    //throw $e;
                    DB::rollBack();
                    return 'error';
                }
            }
            // dd(uniqid());
        } else if ($type == 'tenant-agent') {
            $auction = TenantAgentAuction::where('id', $id)->whereHas('chat_tokens', function ($tokens) use ($user) {
                $tokens->whereHas('chat_users', function ($chat_user) use ($user) {
                    $chat_user->where('user_id', $user->id);
                });
            });
            // dd($auction1->count());
            if ($auction->count()) {
                $auction = $auction->with('chat_tokens', function ($tokens) use ($user) {
                    $tokens->with('chat_users')->whereHas('chat_users', function ($chat_user) use ($user) {
                        $chat_user->where('user_id', $user->id);
                    });
                })->first();
                $chat_token = $auction->chat_tokens->first();
                $route = route('messages', $chat_token->token);
                return redirect()->to($route);
                // dd($chat_token->token);
                // return 'exists';
            } else {
                // dd("other");
                try {
                    DB::beginTransaction(); // Commit
                    $auction = TenantAgentAuction::findOrFail($id);
                    $chat_token = new AuctionChatToken();
                    $chat_token->auction_id = $auction->id;
                    $chat_token->auction_type = 'tenant-agent';
                    $chat_token->token = uniqid();
                    $chat_token->last_message = 'New Chat';
                    $chat_token->save();
                    $chat_user = new AuctionChatUser();
                    $chat_user->auction_chat_token_id = $chat_token->id;
                    $chat_user->user_id = $user->id;
                    $chat_user->save();
                    $chat_user2 = new AuctionChatUser();
                    $chat_user2->auction_chat_token_id = $chat_token->id;
                    $chat_user2->user_id = $auction->user->id;
                    $chat_user2->save();
                    DB::commit();
                    $route = route('messages', $chat_token->token);
                    return redirect()->to($route);
                } catch (\Exception $e) {
                    //throw $e;
                    DB::rollBack();
                    return 'error';
                }
            }
            // dd(uniqid());
        } else if ($type == 'agent-service') {
            $auction = AgentServiceAuction::where('id', $id)->whereHas('chat_tokens', function ($tokens) use ($user) {
                $tokens->whereHas('chat_users', function ($chat_user) use ($user) {
                    $chat_user->where('user_id', $user->id);
                });
            });
            // dd($auction1->count());
            if ($auction->count()) {
                $auction = $auction->with('chat_tokens', function ($tokens) use ($user) {
                    $tokens->with('chat_users')->whereHas('chat_users', function ($chat_user) use ($user) {
                        $chat_user->where('user_id', $user->id);
                    });
                })->first();
                $chat_token = $auction->chat_tokens->first();
                $route = route('messages', $chat_token->token);
                return redirect()->to($route);
                // dd($chat_token->token);
                // return 'exists';
            } else {
                // dd("other");
                try {
                    DB::beginTransaction(); // Commit
                    $auction = AgentServiceAuction::findOrFail($id);
                    $chat_token = new AuctionChatToken();
                    $chat_token->auction_id = $auction->id;
                    $chat_token->auction_type = 'agent-service';
                    $chat_token->token = uniqid();
                    $chat_token->last_message = 'New Chat';
                    $chat_token->save();
                    $chat_user = new AuctionChatUser();
                    $chat_user->auction_chat_token_id = $chat_token->id;
                    $chat_user->user_id = $user->id;
                    $chat_user->save();
                    $chat_user2 = new AuctionChatUser();
                    $chat_user2->auction_chat_token_id = $chat_token->id;
                    $chat_user2->user_id = $auction->user->id;
                    $chat_user2->save();
                    DB::commit();
                    $route = route('messages', $chat_token->token);
                    return redirect()->to($route);
                } catch (\Exception $e) {
                    //throw $e;
                    DB::rollBack();
                    return 'error';
                }
            }
            // dd(uniqid());
        }
    }

    public function messages($token = '')
    {

        $user = Auth::user();
        $token_ids = [];
        if ($user->chat_tokens) {
            $token_ids = $user->chat_tokens->pluck('auction_chat_token_id')->toArray();
        }
        // dd($token_ids);
        $chat_tokens = AuctionChatToken::whereIn('id', $token_ids)->with(['chat_users' => function ($chat_users) use ($user) {
            $chat_users->with('user')->where('user_id', '!=', $user->id);
        }, 'chats'])->withCount(['chats', 'chat_users'])->orderBy('updated_at', 'DESC')->get();
        $last_token = @$chat_tokens[0]->token;
        $page_data['chat_tokens'] = $chat_tokens;
        if ($token != "") {
            $page_data['token'] = $token;
        } else {
            $page_data['token'] = $last_token;
        }
        // dd($chat_tokens->count());
        // dd($chat_tokens->toArray());
        $page_data['title'] = 'Messages';
        return view('messages', $page_data);
    }

    public function load_chat_messages($token)
    {
        // dd('ok');
        $page_data['current_token'] = $current_token = AuctionChatToken::whereToken($token)->firstOrFail();
        $type = $current_token->auction_type;
        if ($type == 'seller-property') {
            $chat_title = $current_token->auction->address;
            $auction_link = route('view-pl', $current_token->auction->id);
        } else if ($type == 'landlord-property') {
            $chat_title = $current_token->auction->address;
            $auction_link = route('agent.landlord.auction', $current_token->auction->id);
        } else if ($type == 'buyer-criteria') {
            $chat_title = $current_token->auction->get->property_type;
            $auction_link = route('buyer.criteria.view', $current_token->auction->id);
        } else if ($type == 'tenant-criteria') {
            $chat_title = $current_token->auction->get->property_type;
            $auction_link = route('tenant.criteria.auction.view', $current_token->auction->id);
        } else if ($type == 'buyer-agent') {
            $chat_title = $current_token->auction->title;
            $auction_link = route('buyer.view-auction', $current_token->auction->id);
        } else if ($type == 'seller-agent') {
            $chat_title = $current_token->auction->address;
            $auction_link = route('seller.agent.auction.detail', $current_token->auction->id);
        } else if ($type == 'landlord-agent') {
            $chat_title = $current_token->auction->address;
            $auction_link = route('landlord.agent.auction.view', $current_token->auction->id);
        } else if ($type == 'tenant-agent') {
            $chat_title = $current_token->auction->title;
            $auction_link = route('tenant.agent.view.auction.view', $current_token->auction->id);
        } else if ($type == 'agent-service') {
            $chat_title = $current_token->auction->get->address;
            $auction_link = route('agent.service.auction.view', $current_token->auction->id);
        } else {
            $chat_title = '';
            $auction_link = '';
        }
        $page_data['chat_title'] = $chat_title;
        $page_data['auction_link'] = $auction_link;
        $view = (string)View::make('messages-ajax', $page_data);
        return response()->json(['success' => true, 'data' => $view]);
    }

    public function chat_bot_reply($token, Request $request)
    {
        $chat_token = AuctionChatToken::whereToken($token)->firstOrFail();
        $auction_id = $chat_token->auction_id;
        $auction_type = $chat_token->auction_type;
        $bot_question = BotQuestion::where('auction_id', $auction_id)->where('auction_type', $auction_type)->where('question', 'LIKE',  $request->message)->first();


        $questions = [];
        if ($auction_type == 'seller-property') {

            $auction = $chat_token->auction;
            $bedrooms = $auction->get->custom_bedrooms == "" ? $auction->get->bedrooms : $auction->get->custom_bedrooms;
            $square_footage = $auction->get->heated_sqft;
            $lot_size = $auction->get->lot_size;
            $water_view = $auction->get->has_water_view;
            $garage = $auction->get->garage;
            $garage_spaces = $auction->get->garage_spaces;
            $pool = $auction->get->garage_spaces;
            $building_elevator = $auction->get->building_elevator;


            if (count($auction->get->appliances) > 1) {
                $appliance = implode(", ", $auction->get->appliances);
            } else {
                $appliance = $auction->get->appliances;
            }
            $flood_zone = $auction->get->is_in_flood_zone;
            $flood_zone_code = $auction->get->flood_zone_code;
            $over_community = $auction->get->old_persons_community;
            $property_condition = $auction->get->prop_condition;
            $rental_restrictions = $auction->get->has_rental_restrictions;

            $questions = [];
            $questions["How many bedrooms does the property have?"] = $bedrooms;
            $questions["What is the square footage of the property?"] = $square_footage;
            $questions["What is the lot size of the property?"] = $lot_size;
            $questions["Does the property have water views? If so, what type of water view?"] = $water_view;
            $questions["Does the property have a garage?"] = $garage;
            $questions["How many garage spaces does the property have?"] = $garage_spaces;
            $questions["Does the property have a pool?"] = $pool;
            $questions["Does the property have an elevator?"] = $building_elevator;
            // $questions["What is the age of the property?"] = "";
            // $questions["Has the seller done any renovations since owning the property?"] = "";
            // $questions["Has the seller pulled any permits for the property?"] = "";
            // $questions["Are there any open permits for the property?"] = "";
            // $questions["Have there been any updates to the property?"] = "";
            // $questions["Have there been any repairs or replacements since the seller owned the property?"] = "";
            // $questions["Are there any repairs or updates needed on the property?"] = "";
            // $questions["Are there any community amenities?"] = "";
            $questions["What appliances are included with the purchase?"] = $appliance;
            // $questions["Is this property being sold furnished or unfurnished?"] = "";
            $questions["Is the property located in a flood zone?"] = $flood_zone;
            $questions["What is the flood zone code?"] = $flood_zone_code;
            $questions["Is the property located in a 55 and over community?"] = $over_community;
            $questions["Are there any restrictions or covenants with the property?"] = $rental_restrictions;
            $questions["What is the condition of the property?"] = $property_condition;
            // $questions["Has the property been well-maintained?"] = "";
            // $questions["What is the minimum down payment to buy this property?"] = "";
            // $questions["Are there any contingencies or special terms that the buyer needs to be aware of before making an offer?"] = "";
            // $questions["How long has the seller owned the property?"] = "";
            // $questions["What is the seller's motivation for selling this property?"] = "";
            // $questions["Has the property ever been subject to any insurance claims, such as floods or fire damage?"] = "";
            // $questions["Are there any pending or recent building permits on the property?"] = "";
            // $questions["How much storage space is available in the property and the surrounding community?"] = "";
            // $questions["How many parking spaces come with the property?"] = "";
            // $questions["Does the property have a carport? If so, how many spaces?"] = "";
            // $questions["Can the buyer negotiate any concessions, such as closing costs or repairs, with the seller?"] = "";
            // $questions["Is the property subject to any liens or encumbrances?"] = "";
            // $questions["Are there any liens or encumbrances on the property?"] = "";
            // $questions["Is the property vacant, tenant-occupied, or seller-occupied?"] = "";
        }

        $searchTerm = $request->message;
        $results = "I can not understand this question";
        $bot_question = CommonBotQuestion::where('question', 'LIKE', $searchTerm)->first();
        if ($bot_question != null) {
            $answer = $bot_question->answer;
        } else {
            foreach ($questions as $question => $answer) {
                if (strpos($question, $searchTerm) !== false) {
                    $results = $answer;
                    break; // Break out of the inner loop if a match is found
                }
            }
            if ($results == "I can not understand this question") {
                // Convert question array to string
                $question_string = implode(", ", array_keys($questions));
                $message_words = explode(" ", $searchTerm);
                // Loop through message words and match to question string
                $matched_questions = array();
                foreach ($message_words as $word) {
                    if (stripos($question_string, $word) !== false) {
                        foreach ($questions as $question => $value) {
                            if (stripos($question, $word) !== false) {
                                $matched_questions[$question] = $value;
                            }
                        }
                    }
                }
                // Output matched questions and show just array keys
                if (!empty($matched_questions)) {
                    $questionMultiple = [];
                    $isMulti = true;
                    foreach ($matched_questions as $question => $value) {
                        $questionMultiple[] = $question;
                    }
                    return response()->json(['success' => true, 'message' => $questionMultiple, 'isMulti' => $isMulti]);
                }
            }
            $msg = $searchTerm ?? "";
            if ($msg) {
                $answer = $results;
            }
        }

        // $chat_token->last_message = $answer;
        // $chat_token->save();
        // $chat = new AuctionChat();
        // $chat->auction_chat_token_id = $chat_token->id;
        // $chat->message = $request->message;
        // $chat->message_type = 'text';
        // $chat->is_bot = 0;
        // $chat->user_id = Auth::user()->id;
        // $chat->save();

        $chat_reply = new AuctionChat();
        $chat_reply->auction_chat_token_id = $chat_token->id;
        $chat_reply->message = $request->message;
        $chat_reply->answer = $answer;
        $chat_reply->message_type = 'text';
        $chat_reply->is_bot = 1;
        $chat_reply->user_id = $chat_token->auction->user->id;
        $chat_reply->save();
        return response()->json(['success' => true, 'message' => $answer]);
    }
}
