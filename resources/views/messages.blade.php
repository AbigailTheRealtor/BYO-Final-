@extends('layouts.main')
@push('styles')
    <style>
        body {
            font-family: "proxima-nova", "Source Sans Pro", sans-serif;
            font-size: 1em;
            letter-spacing: 0.1px;
            color: #32465a;
            text-rendering: optimizeLegibility;
            text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.004);
            -webkit-font-smoothing: antialiased;
        }

        #frame {
            display: flex;
            width: 100%;
            min-width: 360px;
            height: 92vh;
            min-height: 300px;
            max-height: 720px;
            background: #E6EAEA;
        }

        #frame p,
        #frame li {
            font-size: 16px;
        }

        @media screen and (max-width: 360px) {
            #frame {
                width: 100%;
                height: 100vh;
            }
        }

        #frame #sidepanel {
            min-width: 280px;
            max-width: 340px;
            width: 40%;
            height: 100%;
            background: #2c3e50;
            color: #f5f5f5;
            overflow: hidden;
            position: relative;
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel {
                width: 58px;
                min-width: 58px;
            }
        }

        #frame #sidepanel #profile {
            width: 80%;
            margin: 25px auto;
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel #profile {
                width: 100%;
                margin: 0 auto;
                padding: 5px 0 0 0;
                background: #32465a;
            }
        }

        #frame #sidepanel #profile.expanded .wrap {
            height: 210px;
            line-height: initial;
        }

        #frame #sidepanel #profile.expanded .wrap p {
            margin-top: 20px;
        }

        #frame #sidepanel #profile.expanded .wrap i.expand-button {
            -moz-transform: scaleY(-1);
            -o-transform: scaleY(-1);
            -webkit-transform: scaleY(-1);
            transform: scaleY(-1);
            filter: FlipH;
            -ms-filter: "FlipH";
        }

        #frame #sidepanel #profile .wrap {
            height: 60px;
            line-height: 60px;
            overflow: hidden;
            -moz-transition: 0.3s height ease;
            -o-transition: 0.3s height ease;
            -webkit-transition: 0.3s height ease;
            transition: 0.3s height ease;
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel #profile .wrap {
                height: 55px;
            }
        }

        #frame #sidepanel #profile .wrap img {
            width: 50px;
            border-radius: 50%;
            padding: 3px;
            border: 2px solid #e74c3c;
            height: auto;
            float: left;
            cursor: pointer;
            -moz-transition: 0.3s border ease;
            -o-transition: 0.3s border ease;
            -webkit-transition: 0.3s border ease;
            transition: 0.3s border ease;
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel #profile .wrap img {
                width: 40px;
                margin-left: 4px;
            }
        }

        #frame #sidepanel #profile .wrap img.online {
            border: 2px solid #2ecc71;
        }

        #frame #sidepanel #profile .wrap img.away {
            border: 2px solid #f1c40f;
        }

        #frame #sidepanel #profile .wrap img.busy {
            border: 2px solid #e74c3c;
        }

        #frame #sidepanel #profile .wrap img.offline {
            border: 2px solid #95a5a6;
        }

        #frame #sidepanel #profile .wrap p {
            float: left;
            margin-left: 15px;
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel #profile .wrap p {
                display: none;
            }
        }

        #frame #sidepanel #profile .wrap i.expand-button {
            float: right;
            margin-top: 23px;
            font-size: 0.8em;
            cursor: pointer;
            color: #435f7a;
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel #profile .wrap i.expand-button {
                display: none;
            }
        }

        #frame #sidepanel #profile .wrap #status-options {
            position: absolute;
            opacity: 0;
            visibility: hidden;
            width: 150px;
            margin: 70px 0 0 0;
            border-radius: 6px;
            z-index: 99;
            line-height: initial;
            background: #435f7a;
            -moz-transition: 0.3s all ease;
            -o-transition: 0.3s all ease;
            -webkit-transition: 0.3s all ease;
            transition: 0.3s all ease;
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel #profile .wrap #status-options {
                width: 58px;
                margin-top: 57px;
            }
        }

        #frame #sidepanel #profile .wrap #status-options.active {
            opacity: 1;
            visibility: visible;
            margin: 75px 0 0 0;
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel #profile .wrap #status-options.active {
                margin-top: 62px;
            }
        }

        #frame #sidepanel #profile .wrap #status-options:before {
            content: "";
            position: absolute;
            width: 0;
            height: 0;
            border-left: 6px solid transparent;
            border-right: 6px solid transparent;
            border-bottom: 8px solid #435f7a;
            margin: -8px 0 0 24px;
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel #profile .wrap #status-options:before {
                margin-left: 23px;
            }
        }

        #frame #sidepanel #profile .wrap #status-options ul {
            overflow: hidden;
            border-radius: 6px;
        }

        #frame #sidepanel #profile .wrap #status-options ul li {
            padding: 15px 0 30px 18px;
            display: block;
            cursor: pointer;
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel #profile .wrap #status-options ul li {
                padding: 15px 0 35px 22px;
            }
        }

        #frame #sidepanel #profile .wrap #status-options ul li:hover {
            background: #496886;
        }

        #frame #sidepanel #profile .wrap #status-options ul li span.status-circle {
            position: absolute;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin: 5px 0 0 0;
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel #profile .wrap #status-options ul li span.status-circle {
                width: 14px;
                height: 14px;
            }
        }

        #frame #sidepanel #profile .wrap #status-options ul li span.status-circle:before {
            content: "";
            position: absolute;
            width: 14px;
            height: 14px;
            margin: -3px 0 0 -3px;
            background: transparent;
            border-radius: 50%;
            z-index: 0;
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel #profile .wrap #status-options ul li span.status-circle:before {
                height: 18px;
                width: 18px;
            }
        }

        #frame #sidepanel #profile .wrap #status-options ul li p {
            padding-left: 12px;
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel #profile .wrap #status-options ul li p {
                display: none;
            }
        }

        #frame #sidepanel #profile .wrap #status-options ul li#status-online span.status-circle {
            background: #2ecc71;
        }

        #frame #sidepanel #profile .wrap #status-options ul li#status-online.active span.status-circle:before {
            border: 1px solid #2ecc71;
        }

        #frame #sidepanel #profile .wrap #status-options ul li#status-away span.status-circle {
            background: #f1c40f;
        }

        #frame #sidepanel #profile .wrap #status-options ul li#status-away.active span.status-circle:before {
            border: 1px solid #f1c40f;
        }

        #frame #sidepanel #profile .wrap #status-options ul li#status-busy span.status-circle {
            background: #e74c3c;
        }

        #frame #sidepanel #profile .wrap #status-options ul li#status-busy.active span.status-circle:before {
            border: 1px solid #e74c3c;
        }

        #frame #sidepanel #profile .wrap #status-options ul li#status-offline span.status-circle {
            background: #95a5a6;
        }

        #frame #sidepanel #profile .wrap #status-options ul li#status-offline.active span.status-circle:before {
            border: 1px solid #95a5a6;
        }

        #frame #sidepanel #profile .wrap #expanded {
            padding: 100px 0 0 0;
            display: block;
            line-height: initial !important;
        }

        #frame #sidepanel #profile .wrap #expanded label {
            float: left;
            clear: both;
            margin: 0 8px 5px 0;
            padding: 5px 0;
        }

        #frame #sidepanel #profile .wrap #expanded input {
            border: none;
            margin-bottom: 6px;
            background: #32465a;
            border-radius: 3px;
            color: #f5f5f5;
            padding: 7px;
            width: calc(100% - 43px);
        }

        #frame #sidepanel #profile .wrap #expanded input:focus {
            outline: none;
            background: #435f7a;
        }

        #frame #sidepanel #search {
            border-top: 1px solid #32465a;
            border-bottom: 1px solid #32465a;
            font-weight: 300;
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel #search {
                display: none;
            }
        }

        #frame #sidepanel #search label {
            position: absolute;
            margin: 10px 0 0 20px;
        }

        #frame #sidepanel #search input {
            font-family: "proxima-nova", "Source Sans Pro", sans-serif;
            padding: 10px 0 10px 46px;
            width: calc(100% - 25px);
            border: none;
            background: #32465a;
            color: #f5f5f5;
        }

        #frame #sidepanel #search input:focus {
            outline: none;
            background: #435f7a;
        }

        #frame #sidepanel #search input::-webkit-input-placeholder {
            color: #f5f5f5;
        }

        #frame #sidepanel #search input::-moz-placeholder {
            color: #f5f5f5;
        }

        #frame #sidepanel #search input:-ms-input-placeholder {
            color: #f5f5f5;
        }

        #frame #sidepanel #search input:-moz-placeholder {
            color: #f5f5f5;
        }

        #frame #sidepanel #contacts {
            height: calc(100% - 177px);
            overflow-y: scroll;
            overflow-x: hidden;
        }

        #frame #sidepanel #contacts ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel #contacts {
                height: calc(100% - 149px);
                overflow-y: scroll;
                overflow-x: hidden;
            }

            #frame #sidepanel #contacts::-webkit-scrollbar {
                display: none;
            }
        }

        #frame #sidepanel #contacts.expanded {
            height: calc(100% - 334px);
        }

        #frame #sidepanel #contacts::-webkit-scrollbar {
            width: 8px;
            background: #2c3e50;
        }

        #frame #sidepanel #contacts::-webkit-scrollbar-thumb {
            background-color: #243140;
        }

        #frame #sidepanel #contacts ul li.contact {
            position: relative;
            padding: 10px 0 15px 0;
            font-size: 0.9em;
            cursor: pointer;
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel #contacts ul li.contact {
                padding: 6px 0 46px 8px;
            }
        }

        #frame #sidepanel #contacts ul li.contact:hover {
            background: #32465a;
        }

        #frame #sidepanel #contacts ul li.contact.active {
            background: #32465a;
            border-left: 5px solid #435f7a;
        }

        #frame #sidepanel #contacts ul li.contact.active span.contact-status {
            border: 2px solid #32465a !important;
        }

        #frame #sidepanel #contacts ul li.contact .wrap {
            width: 88%;
            margin: 0 auto;
            position: relative;
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel #contacts ul li.contact .wrap {
                width: 100%;
            }
        }

        #frame #sidepanel #contacts ul li.contact .wrap span {
            position: absolute;
            left: 0;
            margin: -2px 0 0 -2px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: 2px solid #2c3e50;
            background: #95a5a6;
        }

        #frame #sidepanel #contacts ul li.contact .wrap span.online {
            background: #2ecc71;
        }

        #frame #sidepanel #contacts ul li.contact .wrap span.away {
            background: #f1c40f;
        }

        #frame #sidepanel #contacts ul li.contact .wrap span.busy {
            background: #e74c3c;
        }

        #frame #sidepanel #contacts ul li.contact .wrap img {
            width: 40px;
            border-radius: 50%;
            float: left;
            margin-right: 10px;
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel #contacts ul li.contact .wrap img {
                margin-right: 0px;
            }
        }

        #frame #sidepanel #contacts ul li.contact .wrap .meta {
            padding: 5px 0 0 0;
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel #contacts ul li.contact .wrap .meta {
                display: none;
            }
        }

        #frame #sidepanel #contacts ul li.contact .wrap .meta .name {
            font-weight: 600;
        }

        #frame #sidepanel #contacts ul li.contact .wrap .meta .preview {
            margin: 5px 0 0 0;
            padding: 0 0 1px;
            font-weight: 400;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            -moz-transition: 1s all ease;
            -o-transition: 1s all ease;
            -webkit-transition: 1s all ease;
            transition: 1s all ease;
            font-size: 13px;
            opacity: 0.3;
        }

        #frame #sidepanel #contacts ul li.contact .wrap .meta .preview span {
            position: initial;
            border-radius: initial;
            background: none;
            border: none;
            padding: 0 2px 0 0;
            margin: 0 0 0 1px;
            opacity: 0.5;
        }

        #frame #sidepanel #bottom-bar {
            position: absolute;
            width: 100%;
            bottom: 0;
        }

        #frame #sidepanel #bottom-bar button {
            float: left;
            border: none;
            width: 50%;
            padding: 10px 0;
            background: #32465a;
            color: #f5f5f5;
            cursor: pointer;
            font-size: 0.85em;
            font-family: "proxima-nova", "Source Sans Pro", sans-serif;
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel #bottom-bar button {
                float: none;
                width: 100%;
                padding: 15px 0;
            }
        }

        #frame #sidepanel #bottom-bar button:focus {
            outline: none;
        }

        #frame #sidepanel #bottom-bar button:nth-child(1) {
            border-right: 1px solid #2c3e50;
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel #bottom-bar button:nth-child(1) {
                border-right: none;
                border-bottom: 1px solid #2c3e50;
            }
        }

        #frame #sidepanel #bottom-bar button:hover {
            background: #435f7a;
        }

        #frame #sidepanel #bottom-bar button i {
            margin-right: 3px;
            font-size: 1em;
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel #bottom-bar button i {
                font-size: 1.3em;
            }
        }

        @media screen and (max-width: 735px) {
            #frame #sidepanel #bottom-bar button span {
                display: none;
            }
        }

        #frame .content {
            width: 60%;
            height: 100%;
            overflow: hidden;
            position: relative;
        }

        @media screen and (max-width: 735px) {
            #frame .content {
                width: calc(100% - 58px);
                min-width: 300px !important;
            }
        }

        @media screen and (min-width: 900px) {
            #frame .content {
                width: calc(100% - 340px);
            }
        }

        #frame .content .contact-profile {
            width: 100%;
            height: 60px;
            line-height: 60px;
            background: #f5f5f5;
        }

        #frame .content .contact-profile img {
            width: 40px;
            border-radius: 50%;
            float: left;
            margin: 9px 12px 0 9px;
        }

        #frame .content .contact-profile p {
            float: left;
        }

        #frame .content .contact-profile .social-media {
            float: right;
        }

        #frame .content .contact-profile .social-media i {
            margin-left: 14px;
            cursor: pointer;
        }

        #frame .content .contact-profile .social-media i:nth-last-child(1) {
            margin-right: 20px;
        }

        #frame .content .contact-profile .social-media i:hover {
            color: #435f7a;
        }

        #frame .content .messages {
            height: auto;
            min-height: calc(100% - 93px);
            max-height: calc(100% - 150px);
            overflow-y: scroll;
            overflow-x: hidden;
        }

        #frame .content .messages ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        @media screen and (max-width: 735px) {
            #frame .content .messages {
                max-height: calc(100% - 105px);
            }
        }

        #frame .content .messages::-webkit-scrollbar {
            width: 8px;
            background: rgba(0, 0, 0, 0);
        }

        #frame .content .messages::-webkit-scrollbar-thumb {
            background-color: rgba(0, 0, 0, 0.3);
        }

        #frame .content .messages ul li {
            display: inline-block;
            clear: both;
            float: left;
            margin: 15px 15px 5px 15px;
            width: calc(100% - 25px);
            font-size: 0.9em;
        }

        #frame .content .messages ul li:nth-last-child(1) {
            margin-bottom: 20px;
        }

        #frame .content .messages ul li.sent img {
            margin: 6px 8px 0 0;
        }

        #frame .content .messages ul li.sent p {
            background: #435f7a;
            color: #f5f5f5;
        }

        #frame .content .messages ul li.sent p.sending_para {
            background: #6c757d;
            color: #f5f5f5;
            display: inline;
            padding-block: 7px;
            cursor: pointer;


        }

        #frame .content .messages ul li.replies img {
            float: right;
            margin: 6px 0 0 8px;
        }

        #frame .content .messages ul li.replies p {
            background: #006e9f;
            color: #FFF;
            float: right;
        }

        #frame .content .messages ul li img {
            width: 22px;
            border-radius: 50%;
            float: left;
        }

        #frame .content .messages ul li p {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 10px;
            max-width: 205px;
            line-height: 130%;
        }

        #frame .content .messages ul li.replies p {
            border-bottom-right-radius: 0;
        }

        #frame .content .messages ul li.sent p {
            border-bottom-left-radius: 0;
        }

        @media screen and (min-width: 735px) {
            #frame .content .messages ul li p {
                max-width: 400px;
            }
        }

        #frame .content .message-input {
            position: absolute;
            bottom: 0;
            width: 100%;
            z-index: 99;
        }

        #frame .content .message-input .wrap {
            position: relative;
        }

        #frame .content .message-input .wrap input {
            font-family: "proxima-nova", "Source Sans Pro", sans-serif;
            border: none;
            width: 100%;
            padding: 11px 32px 10px 8px;
            font-size: 0.8em;
            color: #32465a;
        }

        @media screen and (max-width: 735px) {
            #frame .content .message-input .wrap input {
                padding: 15px 32px 16px 8px;
            }
        }

        #frame .content .message-input .wrap input:focus {
            outline: none;
        }

        #frame .content .message-input .wrap .attachment {
            position: absolute;
            right: 60px;
            z-index: 4;
            margin-top: 10px;
            font-size: 1.1em;
            color: #435f7a;
            opacity: 0.5;
            cursor: pointer;
        }

        @media screen and (max-width: 735px) {
            #frame .content .message-input .wrap .attachment {
                margin-top: 17px;
                right: 65px;
            }
        }

        #frame .content .message-input .wrap .attachment:hover {
            opacity: 1;
        }

        #frame .content .message-input .wrap button {
            border: none;
            width: 50px;
            padding: 12px 0;
            cursor: pointer;
            background: #32465a;
            color: #f5f5f5;
        }

        @media screen and (max-width: 735px) {
            #frame .content .message-input .wrap button {
                padding: 16px 0;
            }
        }

        #frame .content .message-input .wrap button:hover {
            background: #435f7a;
        }

        #frame .content .message-input .wrap button:focus {
            outline: none;
        }

        .question_listing {
            background: #435f7a !important;
            color: #f5f5f5 !important;
        }
    </style>
@endpush
@section('content')
{{-- @dd($page_data); --}}

    <div class="mainDashboard">
        <div class="container">
            {{-- @include('layouts.partials.dashboard_user_section') --}}
            <!-- Section 2  -->
            <div class="messages card">
                <div class="chat-box">

                    {{-- Start Chat Box --}}
                    @php
                        $my_avatar = auth()->user()->avatar ? asset('images/avatar/' . auth()->user()->avatar) : 'https://ppt1080.b-cdn.net/images/avatar/none.png';
                    @endphp
                    <div id="frame">
                        @if ($chat_tokens->count())
                            <div id="sidepanel">
                                <div id="profile" class="bg-black m-0 w-100 p-2">
                                    <div class="wrap">
                                        <div class="d-flex">
                                            <div id="profile-img" style="width:50px; height:50px;">
                                                <img style="width: 100%; height:100%; object-fit:cover; border-color:#000;"
                                                    src="{{ $my_avatar }}" class="online" alt="" />
                                            </div>
                                            {{-- {{auth()->user()->avatar}} --}}
                                            <p class="fw-bold">{{ auth()->user()->name }}</p>
                                        </div>
                                        {{-- <i class="fa fa-chevron-down expand-button" aria-hidden="true"></i> --}}
                                        {{-- <div id="status-options">
                                        <ul>
                                            <li id="status-online" class="active"><span class="status-circle"></span>
                                                <p>Online</p>
                                            </li>
                                            <li id="status-away"><span class="status-circle"></span>
                                                <p>Away</p>
                                            </li>
                                            <li id="status-busy"><span class="status-circle"></span>
                                                <p>Busy</p>
                                            </li>
                                            <li id="status-offline"><span class="status-circle"></span>
                                                <p>Offline</p>
                                            </li>
                                        </ul>
                                    </div> --}}
                                        {{-- <div id="expanded">
                                        <label for="twitter"><i class="fa fa-facebook fa-fw" aria-hidden="true"></i></label>
                                        <input name="twitter" type="text" value="mikeross" />
                                        <label for="twitter"><i class="fa fa-twitter fa-fw" aria-hidden="true"></i></label>
                                        <input name="twitter" type="text" value="ross81" />
                                        <label for="twitter"><i class="fa fa-instagram fa-fw" aria-hidden="true"></i></label>
                                        <input name="twitter" type="text" value="mike.ross" />
                                    </div> --}}
                                    </div>
                                </div>
                                {{-- <div id="search">
                                <label for=""><i class="fa fa-search" aria-hidden="true"></i></label>
                                <input type="text" placeholder="Search contacts..." />
                            </div> --}}
                                <div id="contacts" >
                                    <ul>
                                        @php
                                            $current_token = '';
                                            $current_contact = '';
                                        @endphp
                                        @if(count($chat_tokens)>0)
                                        @foreach ($chat_tokens as $chat_token)
                                        @php

                                                $contact = $chat_token->chat_users->first()->user;
                                                // dd($contact);
                                                if ($chat_token->token == $token) {
                                                    $current_token = $chat_token;
                                                    $current_contact = $contact;
                                                }

                                                // dd('ok');
                                                // dd($chat_token->auction);
                                                // dd($chat_token->auction->address);

                                                $ctype = $chat_token->auction_type;
                                                if ($ctype == 'seller-property') {
                                                    //  dd($chat_token->auction->address);
                                                    // dd('1');
                                                    $chat_title = optional($chat_token->auction)->address;
                                                    // dd('ok');
                                                    // $auction_link = route('view-pl', $chat_token->auction->id);
                                                } elseif ($ctype == 'landlord-property') {
                                                    $chat_title = optional($chat_token->auction)->address;
                                                    // dd('2');
                                                    // $auction_link = route('view-pl', $chat_token->auction->id);
                                                } elseif ($ctype == 'buyer-criteria') {
                                                    // dd('buyer-criteria');
                                                    // $chat_title = $chat_token->title;
                                                    $chat_title = $chat_token->auction->get->property_type;
                                                    // dd('3');
                                                    // dd('3');
                                                    // $auction_link = route('view-pl', $chat_token->auction->id);
                                                } elseif ($ctype == 'tenant-criteria') {
                                                    // $chat_title = $chat_token->title;
                                                    // dd('4');
                                                    $chat_title = $chat_token->auction->get->property_type;
                                                    // $auction_link = route('view-pl', $chat_token->auction->id);
                                                } elseif ($ctype == 'buyer-agent') {
                                                    // dd('5');
                                                    $chat_title = optional($chat_token->auction)->title;
                                                } elseif ($ctype == 'seller-agent') {
                                                    $chat_title = optional($chat_token->auction)->address;
                                                } elseif ($ctype == 'landlord-agent') {
                                                    $chat_title = optional($chat_token->auction)->address;
                                                } elseif ($ctype == 'tenant-agent') {
                                                    $chat_title = optional($chat_token->auction)->address;
                                                } elseif ($ctype == 'agent-service') {
                                                    $chat_title = optional($chat_token->auction)->address;
                                                    // dd('9');
                                                }
                                                $avatar = @$contact->avatar ? asset('images/avatar/' . @$contact->avatar) : 'https://ppt1080.b-cdn.net/images/avatar/none.png';
                                            @endphp
                                            <li class="contact @if ($chat_token->token == $token) active @endif "
                                                data-avatar="{{ $avatar?$avatar:'' }}" data-name="{{ $contact->name?$contact->name:'' }}"
                                                data-token="{{ $chat_token->token }}">
                                                <div class="wrap d-flex">

                                                    {{-- <span class="contact-status online"></span> --}}
                                                    <div style="width:40px; height:40px;">
                                                        <img src="{{ $avatar?$avatar:'' }}" alt=""
                                                            style="width:100%;height:100%;object-fit:cover;" />
                                                    </div>

                                                    <div class="meta ps-2">
                                                        <div>{{ $contact->name?$contact->name:'' }}</div>
                                                        <div class="name"
                                                            style="width:200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                            {{ $chat_title?$chat_title:'' }}</div>

                                                        {{-- <p class="preview">{{$chat_token->last_message}}</p> --}}
                                                    </div>

                                                </div>
                                            </li>
                                            @break
                                        @endforeach
                                        @endif
                                        {{-- {{ dd('ok'); }} --}}

                                    </ul>
                                </div>
                                {{-- <div id="bottom-bar">
                                <button id="addcontact"><i class="fa fa-user-plus fa-fw" aria-hidden="true"></i>
                                    <span>Add contact</span></button>
                                <button id="settings"><i class="fa fa-cog fa-fw" aria-hidden="true"></i>
                                    <span>Settings</span></button>
                            </div> --}}
                            </div>

                            @php
                                $current_contact_avatar = @$current_contact->avatar ? asset('images/avatar/' . @$current_contact->avatar) : 'https://ppt1080.b-cdn.net/images/avatar/none.png';
                            @endphp
                            <div class="content">
                                <div class="contact-profile">
                                    <div class="wrap d-flex">
                                        <div style="width: 40px; height: 40px;">
                                            <img class="contact-profile-img" src="{{ $current_contact_avatar }}"
                                                style="width:100%; height:100%; object-fit:cover;" alt="" />
                                            {{-- {{$current_contact->avatar}} --}}
                                        </div>

                                        <div class="ps-4 fw-bold contact-profile-name">{{ @$current_contact->name }}</div>
                                    </div>
                                </div>
                                <div class="messages">
                                    <div class="chat-cover">
                                        @if ($current_token)
                                            <ul class="chat-messages">
                                                <li>
                                                    {{-- @dd('ok'); --}}
                                                    @php
                                                        $type = $current_token->auction_type;
                                                        if ($type == 'seller-property') {
                                                            $chat_title = optional($chat_token->auction)->address;
                                                            $auction_link = route('view-pl', $current_token->auction->id);
                                                        } elseif ($type == 'landlord-property') {
                                                            $chat_title = optional($chat_token->auction)->address;
                                                            $auction_link = route('agent.landlord.auction', $current_token->auction->id);
                                                        } elseif ($type == 'buyer-criteria') {
                                                            $chat_title = $current_token->auction->get->property_type;
                                                            $auction_link = route('buyer.criteria.view', $current_token->auction->id);
                                                        } elseif ($type == 'tenant-criteria') {
                                                            $chat_title = $current_token->auction->get->property_type;
                                                            $auction_link = route('tenant.criteria.auction.view', $current_token->auction->id);
                                                        } elseif ($type == 'buyer-agent') {
                                                            $chat_title = $current_token->auction->title;
                                                            $auction_link = route('buyer.view-auction', $current_token->auction->id);
                                                        } elseif ($type == 'seller-agent') {
                                                            $chat_title = optional($chat_token->auction)->address;
                                                            $auction_link = route('seller.agent.auction.detail', $current_token->auction->id);
                                                        } elseif ($type == 'landlord-agent') {
                                                            $chat_title = optional($chat_token->auction)->address;
                                                            $auction_link = route('landlord.agent.auction.view', $current_token->auction->id);
                                                        } elseif ($type == 'tenant-agent') {
                                                            $chat_title = $current_token->auction->title;
                                                            $auction_link = route('tenant.agent.view.auction.view', $current_token->auction->id);
                                                        } elseif ($type == 'agent-service') {
                                                            $chat_title = optional($chat_token->auction)->address;
                                                            $auction_link = route('agent.service.auction.view', $current_token->auction->id);
                                                        }
                                                    @endphp
                                                    <a href="{{ $auction_link }}" target="_blank">
                                                        <div class="card fw-bold p-4">{{ $chat_title }}</div>
                                                    </a>
                                                </li>


                                                {{-- @php
                                                   $quations = $current_token->chats->where('is_bot',0);
                                                   $quations = $current_token->chats->where('is_bot',0);

                                                  @endphp


                                                  @foreach ($quations as $item)
                                                    @php
                                                        if ($item->user_id == auth()->id()) {
                                                            $class = 'sent';
                                                        } else {
                                                            $class = 'replies';
                                                        }
                                                    @endphp
                                                    <li class="send">
                                                        <p class="ms-2">{{ $item->message }}</p>
                                                    </li>
                                                @endforeach --}}


                                                @foreach ($current_token->chats as $item)
                                                    @php
                                                        if ($item->user_id == auth()->id()) {
                                                            $class = 'sent';
                                                        } else {
                                                            $class = 'replies';
                                                        }
                                                    @endphp
                                                    {{-- @dump($item->is_bot==0);
                                                    @dd($item->where([]))
                                                    <li class="{{ $class }}">
                                                        <p class="ms-2">{{ $item::select() }}</p>
                                                    </li> --}}
                                                    <li class="send">
                                                        <p class="ms-2 question_listing">{{ $item->message }}</p>
                                                    </li>
                                                    <li class="replies">
                                                        <p class="ms-2">{{ $item->answer }}</p>
                                                    </li>
                                                @endforeach


                                                {{-- <li class="sent d-flex">
                                            <div style="width:22px; height:22px;">
                                                <img src="{{$my_avatar}}" alt="" style="width:100%; height:!00%; object-fit:cover;" />
                                            </div>
                                            <p class="ms-2">How the hell am I supposed to get a jury to believe you when I am not even sure
                                                that I do?!</p>
                                        </li> --}}
                                                {{-- <li class="sent">
                                            <p class="ms-2">How the hell am I supposed to get a jury to believe you when I am not even sure
                                                that I do?!</p>
                                        </li>
                                        <li class="replies">
                                            <p>When you're backed against the wall, break the god damn thing down.</p>
                                        </li>
                                        <li class="replies">
                                            <p>Excuses don't win championships.</p>
                                        </li> --}}
                                            </ul>
                                        @endif
                                        <div style="width:100%;">&nbsp;</div>
                                    </div>
                                </div>

                                <div class="message-input">
                                    <div class="wrap d-flex">
                                        <input type="text" id="input-text" placeholder="Write your message..." />
                                        {{-- <i class="fa fa-paperclip attachment" aria-hidden="true"></i> --}}
                                        <button class="submit mb-0"><i class="fa-solid fa-paper-plane"
                                                aria-hidden="true"></i></button>
                                    </div>
                                </div>

                            </div>
                        @else
                            <div class="d-flex w-100">
                                <div class="d-flex mt-5 pt-5 h4 justify-content-center w-100">
                                    <div style="width:300px; text-align: center;">
                                        <i class="fa fa-comment text-primary fa-6x btn-block mb-4"
                                            style="color:#34465c !important;"></i>
                                        <p class="fw-bold">You have no chat history.</p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    {{-- End Chat Box --}}

                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        $(function() {
            $('.contact').click(function() {
                $('.contact').removeClass('active');
                $(this).addClass('active');
                var avatar = $(this).data('avatar');
                var name = $(this).data('name');
                $('.contact-profile-img').attr('src', avatar);
                $('.contact-profile-name').text(name);
                var token = $(this).data('token');
                $.ajax({
                    type: "GET",
                    url: "{{ route('load_chat_messages', '') }}/" + token,
                    // data: {},
                    dataType: "json",
                    success: function(response) {
                        if (response.success) {
                            $('.chat-messages').html(response.data);
                            var uri = "{{ route('messages') }}/" + token;
                            history.pushState({
                                foo: 'bar'
                            }, '', uri);
                        }
                    }
                });
            });
        });
        $(".messages").animate({
            scrollTop: $(document).height()
        }, "fast");

        $("#profile-img").click(function() {
            $("#status-options").toggleClass("active");
        });

        $(".expand-button").click(function() {
            $("#profile").toggleClass("expanded");
            $("#contacts").toggleClass("expanded");
        });

        $("#status-options ul li").click(function() {
            $("#profile-img").removeClass();
            $("#status-online").removeClass("active");
            $("#status-away").removeClass("active");
            $("#status-busy").removeClass("active");
            $("#status-offline").removeClass("active");
            $(this).addClass("active");

            if ($("#status-online").hasClass("active")) {
                $("#profile-img").addClass("online");
            } else if ($("#status-away").hasClass("active")) {
                $("#profile-img").addClass("away");
            } else if ($("#status-busy").hasClass("active")) {
                $("#profile-img").addClass("busy");
            } else if ($("#status-offline").hasClass("active")) {
                $("#profile-img").addClass("offline");
            } else {
                $("#profile-img").removeClass();
            };

            $("#status-options").removeClass("active");
        });

        function newMessage() {
            message = $(".message-input input").val();
            if ($.trim(message) == '') {
                return false;
            }
            var sent =
                `<li class="sent"><!--<img src="http://emilcarlsson.se/assets/mikeross.png" alt="" /> --><p>${message}</p></li>`;
            var reply = `<li class="replies"><p>${message}</p></li>`;
            /* $('<li class="sent"><!--<img src="http://emilcarlsson.se/assets/mikeross.png" alt="" /> --><p>' + message + '</p></li>')
                .appendTo($('.messages ul')); */
            $('.message-input input').val(null);
            // $('.contact.active .preview').html('<span>You: </span>' + message);
            $('.chat-messages').append(sent);
            // $(".messages").scrollTop($('.chat-cover').height());
            /* $(".messages").animate({
                scrollTop: $('.chat-cover').height()
            }, "fast"); */
            scroll_bottom();
            chat_reply(message);


            /* setTimeout(() => {
                $('.chat-messages').append(reply);
                scroll_bottom();
            }, 1000); */
        };


        function chat_reply(message) {
            var token = $('.contact.active').data('token');
            $.ajax({
                type: "GET",
                url: "{{ route('chat_bot_reply', '') }}/" + token,
                data: {
                    message
                },
                dataType: "json",
                success: function(response) {
                    if (response.success) {
                        setTimeout(() => {
                            if (response.isMulti) {
                                var reply = '';
                                jQuery.each(response.message.slice(0, 5), function(index, value) {

                                    reply =
                                        `${reply} <li class="sent" id="sending_auto" ><p class="sending_para">${value}</p></li>`;


                                });
                                //    reply = `${reply} <li class="sent" ><p class="sending_para">please choose one...</p></li>`;
                                $('.chat-messages').append(reply);
                            } else {
                                var reply = `<li class="replies"><p>${response.message}</p></li>`;
                                $('.chat-messages').append(reply);
                            }

                            scroll_bottom();
                        }, 1000);
                    }
                }
            });
        }

        function scroll_bottom() {
            $(".messages").animate({
                scrollTop: $('.chat-cover').height()
            }, "fast");
        }

        $('.submit').click(function() {
            newMessage();
        });

        $(window).on('keydown', function(e) {
            if (e.which == 13) {
                newMessage();
                return false;
            }
        });

        // copy li / message and send to input field
        $('.chat-messages').on('click', '#sending_auto', function() {
            var replyValue = $(this).text()
                .trim(); // fetch the text content of the clicked li element and remove any leading/trailing whitespace
            var str = $("#input-text").val(replyValue);

            newMessage();

        });
    </script>
@endpush
