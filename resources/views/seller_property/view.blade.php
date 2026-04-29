@extends('layouts.main')
@push('styles')
  {{-- @dd("ok"); --}}
  {{-- @dd($auction); --}}
  <!-- //Listing Description css  -->

  <!-- Toastr CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css">

  <link rel="stylesheet" href="{{ asset('assets/css/listingDescription.css') }}" />


  <style>
    #float-button {
      position: fixed;
      bottom: 20px;
      right: 20px;
      padding: 10px 20px;
      background-color: #007bff;
      color: #fff;
      border: none;
      border-radius: 4px;
      box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.3);
      font-size: 16px;
      z-index: 9999;
    }

    #float-button1 {
      position: fixed;
      bottom: 100px;
      right: 20px;
      padding: 10px 20px;
      background-color: #007bff;
      color: #fff;
      border: none;
      border-radius: 4px;
      box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.3);
      font-size: 16px;
      z-index: 9999;
    }


    #inbox {
      position: fixed;
      bottom: 80px;
      right: 20px;
      width: 300px;
      height: 400px;
      background-color: #fff;
      box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.3);
      display: none;
      padding: 20px;
      border-radius: 4px;
      z-index: 9999;
    }

    #inbox.visible {
      display: block;
    }

    #close-button {
      position: absolute;
      top: 10px;
      right: 10px;
      padding: 5px;
      background-color: #007bff;
      color: #fff;
      border: none;
      border-radius: 4px;
      font-size: 14px;
      cursor: pointer;
    }

    #close-button:hover {
      background-color: #0062cc;
    }

    #message-box {
      display: flex;
      flex-direction: column;
      margin-top: 20px;
    }

    #message-input {
      flex-grow: 1;
      border: 1px solid #ccc;
      border-radius: 4px;
      padding: 10px;
      margin-bottom: 10px;
      resize: none;
      outline: none;
    }

    #send-button {
      align-self: flex-end;
      background-color: #007bff;
      color: #fff;
      border: none;
      border-radius: 4px;
      padding: 5px 10px;
      cursor: pointer;
    }

    #send-button i {
      font-size: 18px;
    }

    #send-button:hover {
      background-color: #0062cc;
    }

    .custom-toast {
      width: 400px !important;
      opacity: 1 !important;
    }
  </style>
  <style>

  </style>
  <style>
    .chat-toggle-btn {
      position: fixed;
      bottom: 20px;
      right: 20px;
      padding: 10px 20px;
      background-color: #333;
      color: #fff;
      border: none;
      border-radius: 5px;
      font-size: 16px;
      cursor: pointer;
      z-index: 9999;
    }

    .chat-container {
      position: fixed;
      bottom: 20px;
      right: 20px;
      width: 300px;
      background-color: #fff;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
      border-radius: 5px;
      overflow: hidden;
      z-index: 9999;
      display: none;
    }

    .chat-header {
      padding: 10px;
      background-color: #333;
      color: #fff;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .chat-header h3 {
      margin: 0;
    }

    .chat-close-btn {
      background: none;
      border: none;
      color: #fff;
      font-size: 18px;
      cursor: pointer;
    }

    .chat-messages {
      padding: 10px;
      max-height: 300px;
      overflow-y: auto;
    }

    .message {
      margin-bottom: 10px;
      padding: 8px;
      border-radius: 5px;
    }

    .incoming {
      background-color: #ebebeb;
    }

    .outgoing {
      background-color: #f1f1f1;
      text-align: right;
    }

    .chat-input {
      display: flex;
      align-items: center;
      padding: 10px;
    }

    #messageInput {
      flex-grow: 1;
      padding: 8px;
      border: none;
      border-radius: 5px;
      margin-right: 10px;
    }

    .send-message-btn {
      background-color: #333;
      color: #fff;
      border: none;
      padding: 8px 16px;
      border-radius: 5px;
      cursor: pointer;
    }
  </style>
  <style>
    /* Chrome, Safari, Edge, Opera */
    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }

    /* Firefox */
    input[type=number] {
      -moz-appearance: textfield;
    }

    .fa-dollar-sign {
      padding: 0 20px;
      background: #facd34;
      color: #fff;
      border: 0;
      font-weight: 700 !important;
      line-height: 39px !important;
      margin-right: -5px;
      z-index: 1;
      border-radius: 3px 0 0 3px;
    }

    .form-control,
    .form-select {
      border-radius: 0.25rem;
      box-shadow: inset 0 1px 2px 0 rgb(66 71 112 / 12%);
      border-radius: 0.25rem;
      background-color: #fafafb;
      margin-bottom: 15px;
    }

    /* .loader {
      width: 40px;
      height: 40px;
      margin: 0 auto;
      border: 4px solid #f3f3f3;
      border-top: 4px solid #3498db;
      border-radius: 50%;
      animation: spin 1s infinite linear;
    }

    @keyframes spin {
      0% {
        transform: rotate(0deg);
      }

      100% {
        transform: rotate(360deg);
      }
    } */

    .removeBold {
      font-weight: normal;
    }

    .biddingOperations {
      gap: 14px;
    }

    /* .listingDescription .right button,
                                                                                                                                                                                                                                                  .listingDescription .rightCol button {
                                                                                                                                                                                                                                                    background: normal !important;
                                                                                                                                                                                                                                                  } */

    /* Counter Bid  */
    .box {
      position: relative;
      border-radius: 3px;
      background: #ffffff;
      border-top: 3px solid #d2d6de;
      margin-bottom: 20px;
      width: 100%;
      box-shadow: 0 1px 1px rgba(0, 0, 0, 0.1);
    }

    .box.box-primary {
      border-top-color: #3c8dbc;
    }

    .box.box-info {
      border-top-color: #00c0ef;
    }

    .box.box-danger {
      border-top-color: #dd4b39;
    }

    .box.box-warning {
      border-top-color: #f39c12;
    }

    .box.box-success {
      border-top-color: #00a65a;
    }

    .box.box-default {
      border-top-color: #d2d6de;
    }

    .box.collapsed-box .box-body,
    .box.collapsed-box .box-footer {
      display: none;
    }

    .box .nav-stacked>li {
      border-bottom: 1px solid #f4f4f4;
      margin: 0;
    }

    .box .nav-stacked>li:last-of-type {
      border-bottom: none;
    }

    .box.height-control .box-body {
      max-height: 300px;
      overflow: auto;
    }

    .box .border-right {
      border-right: 1px solid #f4f4f4;
    }

    .box .border-left {
      border-left: 1px solid #f4f4f4;
    }

    .box.box-solid {
      border-top: 0;
    }

    .box.box-solid>.box-header .btn.btn-default {
      background: transparent;
    }

    .box.box-solid>.box-header .btn:hover,
    .box.box-solid>.box-header a:hover {
      background: rgba(0, 0, 0, 0.1);
    }

    .box.box-solid.box-default {
      border: 1px solid #d2d6de;
    }

    .box.box-solid.box-default>.box-header {
      color: #444;
      background: #d2d6de;
      background-color: #d2d6de;
    }

    .box.box-solid.box-default>.box-header a,
    .box.box-solid.box-default>.box-header .btn {
      color: #444;
    }

    .box.box-solid.box-primary {
      border: 1px solid #3c8dbc;
    }

    .box.box-solid.box-primary>.box-header {
      color: #fff;
      background: #3c8dbc;
      background-color: #3c8dbc;
    }

    .box.box-solid.box-primary>.box-header a,
    .box.box-solid.box-primary>.box-header .btn {
      color: #fff;
    }

    .box.box-solid.box-info {
      border: 1px solid #00c0ef;
    }

    .box.box-solid.box-info>.box-header {
      color: #fff;
      background: #00c0ef;
      background-color: #00c0ef;
    }

    .box.box-solid.box-info>.box-header a,
    .box.box-solid.box-info>.box-header .btn {
      color: #fff;
    }

    .box.box-solid.box-danger {
      border: 1px solid #dd4b39;
    }

    .box.box-solid.box-danger>.box-header {
      color: #fff;
      background: #dd4b39;
      background-color: #dd4b39;
    }

    .box.box-solid.box-danger>.box-header a,
    .box.box-solid.box-danger>.box-header .btn {
      color: #fff;
    }

    .box.box-solid.box-warning {
      border: 1px solid #f39c12;
    }

    .box.box-solid.box-warning>.box-header {
      color: #fff;
      background: #f39c12;
      background-color: #f39c12;
    }

    .box.box-solid.box-warning>.box-header a,
    .box.box-solid.box-warning>.box-header .btn {
      color: #fff;
    }

    .box.box-solid.box-success {
      border: 1px solid #00a65a;
    }

    .box.box-solid.box-success>.box-header {
      color: #fff;
      background: #00a65a;
      background-color: #00a65a;
    }

    .box.box-solid.box-success>.box-header a,
    .box.box-solid.box-success>.box-header .btn {
      color: #fff;
    }

    .box.box-solid>.box-header>.box-tools .btn {
      border: 0;
      box-shadow: none;
    }

    .box.box-solid[class*='bg']>.box-header {
      color: #fff;
    }

    .box .box-group>.box {
      margin-bottom: 5px;
    }

    .box .knob-label {
      text-align: center;
      color: #333;
      font-weight: 100;
      font-size: 12px;
      margin-bottom: 0.3em;
    }

    .box>.overlay,
    .overlay-wrapper>.overlay,
    .box>.loading-img,
    .overlay-wrapper>.loading-img {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%
    }

    .box .overlay,
    .overlay-wrapper .overlay {
      z-index: 50;
      background: rgba(255, 255, 255, 0.7);
      border-radius: 3px;
    }

    .box .overlay>.fa,
    .overlay-wrapper .overlay>.fa {
      position: absolute;
      top: 50%;
      left: 50%;
      margin-left: -15px;
      margin-top: -15px;
      color: #000;
      font-size: 30px;
    }

    .box .overlay.dark,
    .overlay-wrapper .overlay.dark {
      background: rgba(0, 0, 0, 0.5);
    }

    .box-header:before,
    .box-body:before,
    .box-footer:before,
    .box-header:after,
    .box-body:after,
    .box-footer:after {
      content: " ";
      display: table;
    }

    .box-header:after,
    .box-body:after,
    .box-footer:after {
      clear: both;
    }

    .box-header {
      color: #444;
      display: block;
      padding: 10px;
      position: relative;
    }

    .box-header.with-border {
      border-bottom: 1px solid #f4f4f4;
    }

    .collapsed-box .box-header.with-border {
      border-bottom: none;
    }

    .box-header>.fa,
    .box-header>.glyphicon,
    .box-header>.ion,
    .box-header .box-title {
      display: inline-block;
      font-size: 18px;
      margin: 0;
      line-height: 1;
    }

    .box-header>.fa,
    .box-header>.glyphicon,
    .box-header>.ion {
      margin-right: 5px;
    }

    .box-header>.box-tools {
      position: absolute;
      right: 10px;
      top: 5px;
    }

    .box-header>.box-tools [data-toggle="tooltip"] {
      position: relative;
    }

    .box-header>.box-tools.pull-right .dropdown-menu {
      right: 0;
      left: auto;
    }

    .btn-box-tool {
      padding: 5px;
      font-size: 12px;
      background: transparent;
      color: #97a0b3;
    }

    .open .btn-box-tool,
    .btn-box-tool:hover {
      color: #606c84;
    }

    .btn-box-tool.btn:active {
      box-shadow: none;
    }

    .box-body {
      border-top-left-radius: 0;
      border-top-right-radius: 0;
      border-bottom-right-radius: 3px;
      border-bottom-left-radius: 3px;
      padding: 10px;
    }

    .no-header .box-body {
      border-top-right-radius: 3px;
      border-top-left-radius: 3px;
    }

    .box-body>.table {
      margin-bottom: 0;
    }

    .box-body .fc {
      margin-top: 5px;
    }

    .box-body .full-width-chart {
      margin: -19px;
    }

    .box-body.no-padding .full-width-chart {
      margin: -9px;
    }

    .box-body .box-pane {
      border-top-left-radius: 0;
      border-top-right-radius: 0;
      border-bottom-right-radius: 0;
      border-bottom-left-radius: 3px;
    }

    .box-body .box-pane-right {
      border-top-left-radius: 0;
      border-top-right-radius: 0;
      border-bottom-right-radius: 3px;
      border-bottom-left-radius: 0;
    }

    .box-footer {
      border-top-left-radius: 0;
      border-top-right-radius: 0;
      border-bottom-right-radius: 3px;
      border-bottom-left-radius: 3px;
      border-top: 1px solid #f4f4f4;
      padding: 10px;
      background-color: #fff;
    }

    .direct-chat .box-body {
      border-bottom-right-radius: 0;
      border-bottom-left-radius: 0;
      position: relative;
      overflow-x: hidden;
      padding: 0;
    }

    .direct-chat.chat-pane-open .direct-chat-contacts {
      -webkit-transform: translate(0, 0);
      -ms-transform: translate(0, 0);
      -o-transform: translate(0, 0);
      transform: translate(0, 0);
    }

    .direct-chat-messages {
      -webkit-transform: translate(0, 0);
      -ms-transform: translate(0, 0);
      -o-transform: translate(0, 0);
      transform: translate(0, 0);
      padding: 10px;
      height: 250px;
      overflow: auto;
    }

    .direct-chat-msg,
    .direct-chat-text {
      display: block;
    }

    .direct-chat-msg {
      margin-bottom: 10px;
    }

    .direct-chat-msg:before,
    .direct-chat-msg:after {
      content: " ";
      display: table;
    }

    .direct-chat-msg:after {
      clear: both;
    }

    .direct-chat-messages,
    .direct-chat-contacts {
      -webkit-transition: -webkit-transform .5s ease-in-out;
      -moz-transition: -moz-transform .5s ease-in-out;
      -o-transition: -o-transform .5s ease-in-out;
      transition: transform .5s ease-in-out;
    }

    .direct-chat-text {
      border-radius: 5px;
      position: relative;
      padding: 5px 10px;
      background: #d2d6de;
      border: 1px solid #d2d6de;
      margin: 5px 0 0 50px;
      color: #444;
    }

    .direct-chat-text:after,
    .direct-chat-text:before {
      position: absolute;
      right: 100%;
      top: 15px;
      border: solid transparent;
      border-right-color: #d2d6de;
      content: ' ';
      height: 0;
      width: 0;
      pointer-events: none;
    }

    .direct-chat-text:after {
      border-width: 5px;
      margin-top: -5px;
    }

    .direct-chat-text:before {
      border-width: 6px;
      margin-top: -6px;
    }

    .right .direct-chat-text {
      margin-right: 50px;
      margin-left: 0;
    }

    .right .direct-chat-text:after,
    .right .direct-chat-text:before {
      right: auto;
      left: 100%;
      border-right-color: transparent;
      border-left-color: #d2d6de;
    }

    .direct-chat-img {
      border-radius: 50%;
      float: left;
      width: 40px;
      height: 40px;
    }

    .right .direct-chat-img {
      float: right;
    }

    .direct-chat-info {
      display: block;
      margin-bottom: 2px;
      font-size: 12px;
    }

    .direct-chat-name {
      font-weight: 600;
    }

    .direct-chat-timestamp {
      color: #999;
    }

    .direct-chat-contacts-open .direct-chat-contacts {
      -webkit-transform: translate(0, 0);
      -ms-transform: translate(0, 0);
      -o-transform: translate(0, 0);
      transform: translate(0, 0);
    }

    .direct-chat-contacts {
      -webkit-transform: translate(101%, 0);
      -ms-transform: translate(101%, 0);
      -o-transform: translate(101%, 0);
      transform: translate(101%, 0);
      position: absolute;
      top: 0;
      bottom: 0;
      height: 250px;
      width: 100%;
      background: #222d32;
      color: #fff;
      overflow: auto;
    }

    .contacts-list>li {
      border-bottom: 1px solid rgba(0, 0, 0, 0.2);
      padding: 10px;
      margin: 0;
    }

    .contacts-list>li:before,
    .contacts-list>li:after {
      content: " ";
      display: table;
    }

    .contacts-list>li:after {
      clear: both;
    }

    .contacts-list>li:last-of-type {
      border-bottom: none;
    }

    .contacts-list-img {
      border-radius: 50%;
      width: 40px;
      float: left;
    }

    .contacts-list-info {
      margin-left: 45px;
      color: #fff;
    }

    .contacts-list-name,
    .contacts-list-status {
      display: block;
    }

    .contacts-list-name {
      font-weight: 600;
    }

    .contacts-list-status {
      font-size: 12px;
    }

    .contacts-list-date {
      color: #aaa;
      font-weight: normal;
    }

    .contacts-list-msg {
      color: #999;
    }

    .direct-chat-danger .right>.direct-chat-text {
      background: #dd4b39;
      border-color: #dd4b39;
      color: #fff;
    }

    .direct-chat-danger .right>.direct-chat-text:after,
    .direct-chat-danger .right>.direct-chat-text:before {
      border-left-color: #dd4b39;
    }

    .direct-chat-primary .right>.direct-chat-text {
      background: #3c8dbc;
      border-color: #3c8dbc;
      color: #fff;
    }

    .direct-chat-primary .right>.direct-chat-text:after,
    .direct-chat-primary .right>.direct-chat-text:before {
      border-left-color: #3c8dbc;
    }

    .direct-chat-warning .right>.direct-chat-text {
      background: #f39c12;
      border-color: #f39c12;
      color: #fff;
    }

    .direct-chat-warning .right>.direct-chat-text:after,
    .direct-chat-warning .right>.direct-chat-text:before {
      border-left-color: #f39c12;
    }

    .direct-chat-info .right>.direct-chat-text {
      background: #00c0ef;
      border-color: #00c0ef;
      color: #fff;
    }

    .direct-chat-info .right>.direct-chat-text:after,
    .direct-chat-info .right>.direct-chat-text:before {
      border-left-color: #00c0ef;
    }

    .direct-chat-success .right>.direct-chat-text {
      background: #00a65a;
      border-color: #00a65a;
      color: #fff;
    }

    .direct-chat-success .right>.direct-chat-text:after,
    .direct-chat-success .right>.direct-chat-text:before {
      border-left-color: #00a65a;
    }

    table.table.counterAction button {
      padding: 0;
      margin: 0;
      width: auto;
    }
  </style>
@endpush


@section('content')
  @php
    $auth_id = auth()->user() ? auth()->user()->id : 0;
  @endphp
  <!-- Gallery Start Here  -->
  <div class="container listingDescription">
    <div class="row">
      <div class="col-sm-12 col-md-8 col-lg-8 leftCol">
        <div class="d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center justify-content-left">
            @if ($auction->is_approved == 1)
              <span class="badge bg-primary me-2">Active</span>
            @endif
            @if ($auction->is_approved == 0)
              <span class="badge bg-warning me-2">Pending</span>
            @endif
            @if ($auction->is_sold == 1)
              <span class="badge bg-success">Sold</span>
            @endif
          </div>
          @if ($auction->user_id == auth()->user()->id)
            <div class="d-flex justify-content-end align-content-center">
              <a href="{{route('edit-seller-property-listing', $auction->id)}}" class="btn btn-success btn-sm px-3 mb-3 me-2"><i class="fa-solid fa-pen-to-square me-1"></i>Edit Listing</a>
              {{-- <a href="javascript:void(0)" class="btn btn-success btn-sm px-3 mb-3"><i class="fa-solid fa-pen-to-square me-1"></i>Edit Auction Status</a> --}}
            </div>
          @endif
        </div>
        @php
            $_sellerAiFaq = json_decode($auction->info('listing_ai_faq') ?: '{}', true) ?? [];
            $_sellerIsOwner = auth()->check() && $auction->user_id == auth()->id();
        @endphp
        <x-listing-ai-knowledge-base
            listing-type="seller"
            :listing-id="$auction->id"
            :is-owner="$_sellerIsOwner"
            :ai-faq="$_sellerAiFaq"
            :share-token="$auction->ai_share_token"
        />
        <!-- Modal -->
        @if (@$auction->get->photos)
          <div class="modal fade" id="lightbox" role="dialog" tabindex="-1" aria-labelledby="exampleModalLabel"
            aria-hidden="true">
            <div class="modal-dialog" role="document">
              <div class="modal-content">
                <button type="button" class="close text-right p-2" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
                <div id="indicators" class="carousel slide" data-interval="false">
                  <ol class="carousel-indicators">
                    @foreach (@$auction->get->photos as $image)
                      <li data-target="#indicators" data-slide-to="{{ $loop->iteration }}" class="active">
                      </li>
                    @endforeach
                  </ol>
                  <div class="carousel-inner">
                    @php
                      $i = 1;
                    @endphp
                    @foreach (@$auction->get->photos as $image)
                      <div class="carousel-item {{ $i++ == 1 ? 'active' : '' }}">
                        <img class="d-block w-100" src="{{ asset($image) }}" alt="First slide">
                      </div>
                    @endforeach

                  </div>
                  <a class="carousel-control-prev" href="#indicators" role="button" data-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="sr-only">Previous</span>
                  </a>
                  <a class="carousel-control-next" href="#indicators" role="button" data-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="sr-only">Next</span>
                  </a>
                </div>

              </div>
            </div>
          </div>
        @endif
        <!-- End  -->
        <!-- Description Box  -->
        <div class="card description">
          <div class="card-body">
            <h4>Marketing Materials:</h4>
            <hr>
            <!-- Galery Start Here  -->
            <div class="row" style="flex-wrap: wrap;">
              <div class="col-md-12 col-12 fw-bold mt-1 mb-1" data-toggle="modal" data-target="#lightbox">
                Property Photos:
                <!-- Main Video Baner  -->
                @php
                  $i_sr = 0;
                  $mediaImage = url('auction/images/noimage.png');
                  if (gettype(@$auction->get->photos) == 'array') {
                      $photos = @$auction->get->photos;
                      $mediaImage = url(current($photos));
                  }
                @endphp
                <div class="col-sm-12 col-md-6 col-lg-8">
                  <img class="w-100" src="{{ asset(@$mediaImage) }}" data-target="#indicators"
                    data-slide-to="{{ $i_sr }}" alt="" />
                </div>
                <!-- Small Images  -->
                <div class="col-sm-12 col-md-4 col-lg-4">
                  <div class="row">
                    {{-- @dd(@$auction->get) --}}
                    @if (@$auction->get->photos)
                      @foreach (@$auction->get->photos as $image)
                        <div class="col-sm-4 col-md-6 col-lg-6 p-2">
                          <img class="w-100" src="{{ asset($image) }}" data-target="#indicators"
                            data-slide-to="{{ $i_sr++ }}" alt="" />
                        </div>
                      @endforeach
                    @else
                      <div class="col-sm-4 col-md-6 col-lg-6 p-2">
                        <img class="w-100" src="{{ asset($mediaImage) }}" data-target="#indicators"
                          data-slide-to="{{ $i_sr++ }}" alt="" />
                      </div>
                    @endif
                  </div>
                </div>
              </div>
              
              @if (isset($auction->get->video_type) && $auction->get->video_type == 'video_upload')
                @if (isset($auction->get->video) && $auction->get->video !== null)
                  <div class="col-md-12 col-12 fw-bold mt-2 mb-1">
                    Property Video:
                    <span class="removeBold">
                      <video src="{{ asset($auction->get->video) }}" style="width:100%;height:29vh;"
                          controls autoplay></video>
                    </span>
                  </div>
                @endif
              @elseif (isset($auction->get->video_type) && $auction->get->video_type == 'youtube_video')
                @if (isset($auction->get->youtube_video_link) && $auction->get->youtube_video_link !== null)
                  <div class="col-md-12 col-12 fw-bold mt-2 mb-1">
                    Property Video:
                    <iframe width="560" height="315" src="{{$auction->get->youtube_video_link}}" 
                      title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                      referrerpolicy="strict-origin-when-cross-origin" allowfullscreen>
                    </iframe>
                  </div>
                @endif
              @else 
                @if (isset($auction->get->vimeo_video_link) && $auction->get->vimeo_video_link !== null)
                  <div class="col-md-12 col-12 fw-bold mt-2 mb-1">
                    Property Video:
                    <iframe src="{{$auction->get->vimeo_video_link}}" width="640" height="360" frameborder="0" allow="autoplay; fullscreen" allowfullscreen></iframe>
                  </div>
                @endif
              @endif
              @if (isset($auction->get->floor_plan) && $auction->get->floor_plan !== null)
                <div class="col-md-12 col-12 fw-bold mt-2 mb-1">
                  Floor Plan:
                  @if (isset($auction->get->floor_plan) && $auction->get->floor_plan !== null && !is_array($auction->get->floor_plan))
                    <span class="removeBold">
                      <a href="{{ asset($auction->get->floor_plan) }}" class="d-block" download><img src="{{ asset($auction->get->floor_plan) }}" style="width:100px;height:130px;" /></a>
                    </span>
                  @endif
                </div>
              @endif
              @if (isset($auction->get->disclosures) && $auction->get->disclosures !== null && is_array($auction->get->disclosures))
                <div class="col-md-12 col-12 fw-bold mt-2 mb-1">
                  Addendums/Disclosure:
                  @if ($auction->get->disclosures !== null && is_array($auction->get->disclosures) && count($auction->get->disclosures) > 0)
                  <div>
                    @foreach ($auction->get->disclosures as $item)
                    <span class="removeBold">
                      <a href="{{ asset($item) }}" download><img src="{{ asset($item) }}" style="width:100px;height:130px;" /></a>
                    </span>
                    @endforeach
                  </div>
                  @endif
                </div>
              @endif
              @if (isset($auction->get->three_d_tour))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  3D Tour: <a href="{{ @$auction->get->three_d_tour }}" target="_blank"
                    rel="noopener noreferrer"><span class="removeBold"> {{ @$auction->get->three_d_tour }}</span></a>
                </div>
              @endif
            </div>
            <hr />
            <h4>Description:</h4>
            <p class="card-text">{{ @$auction->get->description }}</p>
            <hr />
            {{-- Price & Terms start --}}
            <h4>Price and Terms:</h4>
            @if ($auction->get->auction_type == 'Traditional Listing')
              <div class="row" style="flex-wrap: wrap;">
                @if (isset($auction->get->price) && $auction->get->price !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Price: <span class="removeBold">${{ @$auction->get->price }}</span> 
                  </div>
                @endif
                @if (isset($auction->get->price_per_sqfeet) && $auction->get->price_per_sqfeet !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    List Price Per Sqf: <span class="removeBold">${{ @$auction->get->price_per_sqfeet}}</span> 
                  </div>
                @endif
                @if (isset($auction->get->escrow_amount) && $auction->get->escrow_amount !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Acceptable Escrow Deposit: <span class="removeBold">${{ @$auction->get->escrow_amount2}}</span> 
                  </div>
                @endif
                @if (isset($auction->get->closing_days2) && $auction->get->closing_days2 !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Number of Days the Seller Will Accept For Closing: <span class="removeBold">{{ @$auction->get->closing_days2}}</span> 
                  </div>
                @endif
                @if (isset($auction->get->contigencies_accepted_by_seller) && is_array($auction->get->contigencies_accepted_by_seller))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Acceptable Contingencies: 
                    @foreach ($auction->get->contigencies_accepted_by_seller as $contigency)
                      <span class="d-inline-block removeBold">
                        {{ $contigency }}
                      </span> 
                      @if ($contigency == 'Inspection contingency')
                        <span class="badge bg-secondary removeBold">{{ $auction->get->inspection . ','}}</span> 
                      @elseif ($contigency == 'Appraisal contingency')
                        <span class="badge bg-secondary removeBold">{{ $auction->get->appraisal . ','}}</span> 
                      @elseif ($contigency == 'Financing contingency')
                        <span class="badge bg-secondary removeBold">{{ $auction->get->finance . ','}}</span>
                      @elseif ($contigency == 'Sale of a property contingency')
                        <span class="badge bg-secondary removeBold">{{ $auction->get->saleContingency . ','}}</span>
                      @elseif ($contigency == 'Other')
                        <span class="badge bg-secondary removeBold">{{ $auction->get->acceptable}}</span>
                        <span class="badge bg-secondary removeBold">{{ $auction->get->acceptable_days . ','}}</span>
                      @endif
                    @endforeach
                  </div>
                @endif
                @if (isset($auction->get->sellerOffer) && $auction->get->sellerOffer !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Offered Credit to Buyer at Closing: <span class="removeBold">${{ @$auction->get->sellerOffer}}</span> 
                  </div>
                @endif
                @if (isset($auction->get->otherFinancing) && $auction->get->otherFinancing !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Acceptable Currency/Financing: <span class="removeBold">${{ @$auction->get->otherFinancing}}</span> 
                  </div>
                @endif
                @if (isset($auction->get->expiration_date) && $auction->get->expiration_date !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Time Frame Allocated to Respond Offers: <span class="removeBold">{{ @$auction->get->expiration_date}}</span> 
                  </div>
                @endif
              </div>
            @elseif ($auction->get->auction_type == 'Auction Listing')
              @if(isset($auction->get->buy_now_price) && $auction->get->buy_now_price !== null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Buy Now Price: <span class="removeBold">${{ @$auction->get->buy_now_price}}</span> 
                </div>
              @endif
              @if (isset($auction->get->buy_now_price_per_sqfeet) && $auction->get->buy_now_price_per_sqfeet !== null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Buy Now Price Per Sqft: <span class="removeBold">${{ @$auction->get->buy_now_price_per_sqfeet}}</span> 
                </div>
              @endif
              @if (isset($auction->get->starting_price) && $auction->get->starting_price !== null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Starting Price: <span class="removeBold">${{ @$auction->get->starting_price}}</span> 
                </div>
              @endif
              {{-- @if (isset($auction->get->reserve_price) && $auction->get->reserve_price !== null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Reserve Price: <span class="removeBold">${{ @$auction->get->reserve_price}}</span> 
                </div>
              @endif --}}
              @if (isset($auction->get->escrow_amount) && $auction->get->escrow_amount !== null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Acceptable Escrow Deposit: <span class="removeBold">${{ @$auction->get->escrow_amount}}</span> 
                </div>
              @endif
              @if (isset($auction->get->closing_days2) && $auction->get->closing_days2 !== null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Number of Days the Seller Will Accept For Closing: <span class="removeBold">{{ @$auction->get->closing_days2}}</span> 
                </div>
              @endif
              @if (isset($auction->get->contigencies_accepted_by_seller) && is_array($auction->get->contigencies_accepted_by_seller))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Acceptable Contingencies: 
                  @foreach ($auction->get->contigencies_accepted_by_seller as $contingency)
                    <span class="d-inline-block removeBold">
                      {{ $contingency}}
                    </span> 
                    @if ($auction->auction_type === 'Traditional Listing')
                      @if ($contingency == 'Inspection contingency')
                      <span class="badge bg-secondary removeBold">{{ $auction->get->inspection}}</span> 
                      @elseif ($contingency == 'Appraisal contingency')
                        <span class="badge bg-secondary removeBold">{{ $auction->get->appraisal}}</span> 
                      @elseif ($contingency == 'Financing contingency')
                        <span class="badge bg-secondary removeBold">{{ $auction->get->finance}}</span>
                      @elseif ($contingency == 'Sale of a property contingency')
                        <span class="badge bg-secondary removeBold">{{ $auction->get->saleContingency}}</span>
                      @elseif ($contingency == 'Other')
                        <span class="badge bg-secondary removeBold">{{ $auction->get->acceptable}}</span>
                        <span class="badge bg-secondary removeBold">{{ $auction->get->acceptable_days}}</span>
                      @endif
                    @else
                      @if ($contingency == 'Inspection contingency')
                      <span class="badge bg-secondary removeBold">{{ $auction->get->inspection_auction}}</span> 
                      @elseif ($contingency == 'Appraisal contingency')
                        <span class="badge bg-secondary removeBold">{{ $auction->get->appraisal_auction}}</span> 
                      @elseif ($contingency == 'Financing contingency')
                        <span class="badge bg-secondary removeBold">{{ $auction->get->finance_auction}}</span>
                      @elseif ($contingency == 'Sale of a property contingency')
                        <span class="badge bg-secondary removeBold">{{ $auction->get->saleContingency_auction}}</span>
                      @elseif ($contingency == 'Other')
                        <span class="badge bg-secondary removeBold">{{ $auction->get->acceptable_auction}}</span>
                        <span class="badge bg-secondary removeBold">{{ $auction->get->acceptable_days_auction}}</span>
                      @endif
                    @endif
                    
                  @endforeach
                </div>
              @endif'
              @if (isset($auction->get->sellerOffer) && $auction->get->sellerOffer !== null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Offered Credit to Buyer at Closing: <span class="removeBold">${{ @$auction->get->sellerOffer}}</span> 
                </div>
              @endif
              @if (isset($auction->get->otherFinancing) && $auction->get->otherFinancing !== null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Acceptable Currency/Financing: <span class="removeBold">${{ @$auction->get->otherFinancing}}</span> 
                </div>
              @endif
            @endif
            {{-- Price & Terms end --}}
            <hr>
            {{-- Listing Information start --}}
            <h4>Listing Information:</h4>
            <div class="row" style="flex-wrap: wrap;">
              <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                Address: <span class="removeBold">{{ @$auction->get->address }}</span> 
              </div>
              {{-- <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                City: <span class="removeBold">{{ @$auction->get->city }} </span>
              </div> --}}
              <div class="col-md-12 col-12 fw-bold mt-1 mb-1">
                <i class="fa-regular fa-check-square"></i>
                County: <span class="removeBold">{{ @$auction->get->county }} </span>
              </div>
              {{-- <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                State: <span class="removeBold">{{ @$auction->get->state }} </span>
              </div> --}}
              @if (@$auction->get->listing_date != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> 
                  Listing Date: <span class="removeBold">{{ date('Y-m-d', strtotime($auction->get->listing_date)) }}
                  </span>
                </div>
              @endif
              @if (@$auction->get->expiration_date != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> 
                  Listing Expiration Date: <span class="removeBold"> {{ date('Y-m-d', strtotime($auction->get->expiration_date)) }}
                  </span>
                </div>
              @endif
              @if (@$auction->get->service_type != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Listing Service Type: <span class="removeBold">{{ @$auction->get->service_type }}</span> 
                </div>
              @endif
              @if (@$auction->get->representation != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Representation: <span class="removeBold">{{ @$auction->get->representation }}</span> 
                </div>
              @endif
              @if (@$auction->get->auction_type != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Listing Type: <span class="removeBold">{{ @$auction->get->auction_type }}</span> 
                </div>
              @endif
              @if (@$auction->get->property_type != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                  Property Style: <span class="d-inline-block removeBold"> {{ @$auction->get->property_type }} </span>
                  @if ($auction->get->property_items !== null)
                    <span class="badge bg-secondary removeBold">{{ @$auction->get->property_items}}</span>
                  @endif
                </div>
              @endif
              @if (@$auction->get->special_sale != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Special Sale Provision: <span class="removeBold">{{ @$auction->get->special_sale }} </span>
                </div>
              @endif
              @if (@$auction->get->prop_condition != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                  Property Condition: <span class="removeBold"> {{ @$auction->get->prop_condition }} </span>
                </div>
              @endif
              {{-- Listing Information end --}}
              {{-- Unit Details and Financial Performance Overview start --}}
              <div class="d-flex" style="flex-wrap: wrap;">
                @if ($auction->get->property_type == 'Income Property')
                <hr>
                <h4>Unit Details and Financial Performance Overview:</h4>
                @if (isset($auction->get->unit_type) && $auction->get->unit_type !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Unit Type: <span class="removeBold"> {{ @$auction->get->unit_type }} </span>
                  </div>
                @endif
                @if (isset($auction->get->beds_unit) && $auction->get->beds_unit !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Beds/Unit: <span class="removeBold"> {{ @$auction->get->beds_unit }} </span>
                  </div>
                @endif
                @if (isset($auction->get->baths_unit) && $auction->get->baths_unit !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Baths/Unit: <span class="removeBold"> {{ @$auction->get->baths_unit }} </span>
                  </div>
                @endif
                @if (isset($auction->get->sqt_ft_heated) && $auction->get->sqt_ft_heated !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Sqft Heated: <span class="removeBold"> {{ @$auction->get->sqt_ft_heated }} </span>
                  </div>
                @endif
                @if (isset($auction->get->custom_occupied) && $auction->get->custom_occupied !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Number of Occupied Units: <span class="removeBold"> {{ @$auction->get->custom_occupied }} </span>
                  </div>
                @endif
                @if (isset($auction->get->current_rent) && $auction->get->current_rent !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Current Rent: <span class="removeBold"> {{ @$auction->get->current_rent }} </span>
                  </div>
                @endif
                @if (isset($auction->get->garage_spaces_unit) && $auction->get->garage_spaces_unit !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Garage Spaces: <span class="removeBold"> {{ @$auction->get->garage_spaces_unit }} </span>
                  </div>
                @endif
                @if (isset($auction->get->unit_type_of_description) && $auction->get->unit_type_of_description !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Unit Type Description <span class="removeBold"> {{ @$auction->get->unit_type_of_description }} </span>
                  </div>
                @endif
                @if (isset($auction->get->annual_gross_income) && $auction->get->annual_gross_income !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Annual Gross Income: <span class="removeBold"> {{ @$auction->get->annual_gross_income }} </span>
                  </div>
                @endif
                @if (isset($auction->get->total_monthly_rent) && $auction->get->total_monthly_rent !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Total Monthly Rent: <span class="removeBold"> {{ @$auction->get->total_monthly_rent }} </span>
                  </div>
                @endif
                @if (isset($auction->get->total_monthly_expenses) && $auction->get->total_monthly_expenses !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Total Monthly Expenses: <span class="removeBold"> {{ @$auction->get->total_monthly_expenses }} </span>
                  </div>
                @endif
                @if (isset($auction->get->annual_net_income) && $auction->get->annual_net_income !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Annual Net Income: <span class="removeBold"> {{ @$auction->get->annual_net_income }} </span>
                  </div>
                @endif
                @if (isset($auction->get->est_annual_market_income) && $auction->get->est_annual_market_income !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Est Annual Market Income: <span class="removeBold"> {{ @$auction->get->est_annual_market_income }} </span>
                  </div>
                @endif
                @if (isset($auction->get->annual_expenses) && $auction->get->annual_expenses !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Annual Expenses: <span class="removeBold"> {{ @$auction->get->annual_expenses }} </span>
                  </div>
                @endif
                @if (isset($auction->get->length_of_lease) && $auction->get->length_of_lease !== null && is_array($auction->get->length_of_lease))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Length of Lease: 
                    @foreach ($auction->get->length_of_lease as $item)
                      <span class="d-inline-block removeBold"> {{ $item }} </span>
                    @endforeach
                    @if (isset($auction->get->custom_leases_length) && $auction->get->custom_leases_length !== null)
                      <span class="d-inline-block removeBold"> {{ $auction->get->custom_leases_length }} </span>
                    @endif                    
                  </div>
                @endif
                @if (isset($auction->get->terms_of_lease) && $auction->get->terms_of_lease !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Terms of Lease: <span class="d-inline-block removeBold"> {{ @$auction->get->terms_of_lease }} </span>
                  </div>
                  @if (isset($auction->get->otherTermLease) && $auction->get->otherTermLease !== null)
                    <span class="d-inline-block removeBold"> {{ $auction->get->otherTermLease }} </span>
                  @endif  
                @endif
                @if (isset($auction->get->tenant_pays) && $auction->get->tenant_pays !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Tenant Pays: <span class="d-inline-block removeBold"> {{ @$auction->get->tenant_pays }} </span>
                  </div>
                  @if (isset($auction->get->otherTenantPay) && $auction->get->otherTenantPay !== null)
                    <span class="d-inline-block removeBold"> {{ $auction->get->otherTenantPay }} </span>
                  @endif  
                @endif
                @if (isset($auction->get->financial_sources) && $auction->get->financial_sources !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Financial Source <span class="removeBold"> {{ @$auction->get->financial_sources }} </span>
                  </div>
                @endif
                @if (isset($auction->get->total_number_of_units) && $auction->get->total_number_of_units !== null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Total Number of Units: <span class="removeBold"> {{ @$auction->get->total_number_of_units }} </span>
                  </div>
                @endif
                @endif
                {{-- Unit Details and Financial Performance Overview end --}}
                

                  {{--
                @if(@$auction->get->auction_type =='Traditional (No Timer)')
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Traditional Listings:</div>
                <div class="mx-4">
                  @if (@$auction->get->price != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Price: <span class="removeBold"> {{ @$auction->get->price }} </span></div>
                  @endif
                  @if (@$auction->get->escrow_amount2 != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Acceptable Escrow Deposit: <span class="removeBold"> {{ @$auction->get->escrow_amount2 }} </span></div>
                  @endif
                  @if (@$auction->get->closing_days2 != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Number of Days the Seller Will Accept For Closing: <span class="removeBold"> {{ @$auction->get->closing_days2 }} </span></div>
                  @endif
                  @if (@$auction->get->contigencies_accepted_by_seller != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Acceptable Contingencies: <span class="removeBold"> {{ @$auction->get->contigencies_accepted_by_seller }} </span></div>
                  @endif
                  @if (@$auction->get->inspection != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Inspection contingency (days): <span class="removeBold"> {{ @$auction->get->inspection }} </span></div>
                  @endif
                  @if (@$auction->get->appraisal != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Appraisal contingency (days): <span class="removeBold"> {{ @$auction->get->appraisal }} </span></div>
                  @endif
                  @if (@$auction->get->finance != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Finance contingency (days): <span class="removeBold"> {{ @$auction->get->finance }} </span></div>
                  @endif
                  @if (@$auction->get->saleContingency != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Sale of a property contingency (days): <span class="removeBold"> {{ @$auction->get->saleContingency }} </span></div>
                  @endif
                  @if (@$auction->get->acceptable != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Acceptable contingency: <span class="removeBold"> {{ @$auction->get->acceptable }} </span></div>
                  @endif
                  @if (@$auction->get->timeFrame != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Time Frame Allocated to Respond Offers:<span class="removeBold"> {{ @$auction->get->timeFrame }} </span></div>
                  @endif
                  @if (@$auction->get->timeFrame != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Time Frame Allocated to Respond to Multiple Offers:<span class="removeBold"> {{ @$auction->get->timeFrame }} </span></div>
                  @endif
                </div>
                @endif
                @if(@$auction->get->auction_type =='Auction (Timer)')
                <h5>Price and Terms:</h5>
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Auction Listings (Under Seller’s Terms):</div>
                <div class="mx-4">
                  @if (@$auction->get->buy_now_price != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Buy Now Price: <span class="removeBold"> ${{ @$auction->get->buy_now_price }} </span></div>
                  @endif
                  @if (@$auction->get->starting_price != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Starting Price: <span class="removeBold"> ${{ @$auction->get->starting_price }} </span></div>
                  @endif
                  @if (@$auction->get->reserve_price != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Reserve Price: <span class="removeBold"> ${{ @$auction->get->reserve_price }} </span></div>
                  @endif
                  @if (@$auction->get->escrow_amount != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Acceptable Escrow Deposit: <span class="removeBold"> ${{ @$auction->get->escrow_amount }} </span></div>
                  @endif
                  @if (@$auction->get->closing_days != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Number of Days the Seller Will Accept For Closing: <span class="removeBold"> {{ @$auction->get->closing_days }} </span></div>
                  @endif
                  @if (@$auction->get->contigencies_accepted_by_seller != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Acceptable Contingencies: <span class="removeBold"> {{ @$auction->get->contigencies_accepted_by_seller }} </span></div>
                  @endif
                  @if (@$auction->get->inspection != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Inspection contingency (days): <span class="removeBold"> {{ @$auction->get->inspection }} </span></div>
                  @endif
                  @if (@$auction->get->appraisal != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Appraisal contingency (days): <span class="removeBold"> {{ @$auction->get->appraisal }} </span></div>
                  @endif
                  @if (@$auction->get->finance != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Finance contingency (days): <span class="removeBold"> {{ @$auction->get->finance }} </span></div>
                  @endif
                  @if (@$auction->get->saleContingency != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Sale of a property contingency (days): <span class="removeBold"> {{ @$auction->get->saleContingency }} </span></div>
                  @endif
                  @if (@$auction->get->acceptable != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Acceptable contingency: <span class="removeBold"> {{ @$auction->get->acceptable }} </span></div>
                  @endif
                  @if (@$auction->get->sellerOffer != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Is the seller offering a credit to the buyer at closing?<span class="removeBold"> {{ @$auction->get->sellerOffer }} </span></div>
                  @endif
                </div>
                @endif
                
            
                @if (@$auction->get->financings != null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Acceptable Currency/ Financing:
                    @if (gettype(@$auction->get->financings) == 'array')
                      @foreach (@$auction->get->financings as $item)
                        <span class="badge bg-secondary removeBold">
                          <{{ $item }}< /span>
                      @endforeach
                    @endif
                  </div>
                @endif 
                @if (@$auction->get->ptesAllowed != null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Pets Allowed:
                  </div>
                  @if (@$auction->get->acceptablePet != null)
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                      Acceptable Pet Types: <span class="removeBold"> {{ @$auction->get->acceptablePet }}
                      </span>
                    </div>
                    @endif
                    @if (@$auction->get->total_pets_allowed != null)
                      <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                        Number of Pets Allowed: <span class="removeBold"> {{ @$auction->get->total_pets_allowed !='Other' ? @$auction->get->total_pets_allowed: @$auction->get->custom_pets_allowed }}
                        </span>
                      </div>
                    @endif
                @endif
                @if (@$auction->get->max_pet_weight != null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Max Pet Weight: <span class="removeBold"> {{ @$auction->get->max_pet_weight }} </span> </div>
                @endif
                @if (@$auction->get->has_water_extra != null && @$auction->get->has_water_extra != null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Water Extra: <span class="removeBold"> {{ @$auction->get->has_water_extra }}</span> </div>
                @endif
                @if (@$auction->get->water_extras != null && @$auction->get->water_extras != 'null')
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Water Extras:
                    @if (gettype(@$auction->get->water_extras) == 'array')
                      @foreach (@$auction->get->water_extras as $item)
                        <span class="badge bg-secondary removeBold">{{ $item }}</span>
                      @endforeach
                    @endif
                  </div>
                @endif
                
                @if (@$auction->get->has_leasing != null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Can the property be leased? <span class="removeBold"> {{ @$auction->get->has_leasing !='Yes' ? @$auction->get->has_leasing: @$auction->get->has_lease_restriction }}
                    </span>
                  </div>
                @endif
                
                
                
                
                
                
                
                
                
                
                @if (@$auction->get->description != null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Description: <span class="removeBold"> {{ @$auction->get->description }}
                    </span>
                  </div>
                @endif
                @if (@$auction->get->hoa_contact != null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Contact information for the Association Manager: <span class="removeBold">
                      {{ @$auction->get->hoa_contact }} </span> </div>
                @endif
                @if (@$auction->get->old_persons_community != null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 55+
                    over community: <span class="removeBold"> {{ @$auction->get->old_persons_community }} </span>
                  </div>
                @endif
                @if (@$auction->get->has_rental_restrictions != null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Rental Restrictions: <span class="removeBold"> {{ @$auction->get->has_rental_restrictions }}
                    </span>
                  </div>
                @endif
                @if (@$auction->get->rental_restrictions != null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> If
                    Rental Restrictions list: <span class="removeBold"> {{ @$auction->get->rental_restrictions }}
                    </span>
                  </div>
                @endif
                @if (@$auction->get->mls_id != null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> MLS
                    ID: <span class="removeBold"> {{ @$auction->get->mls_id }}</span> </div>
                @endif
                --}}
              </div>
              <hr>
              {{-- Real Estate Included --}}
              @if ($auction->get->property_type == 'Business Opportunity')
                <h4>Real Estate Included</h4>
                @if (isset($auction->get->has_real_estate_include))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Real Estate Included: <span class="removeBold"> {{ @$auction->get->has_real_estate_include }}</span> 
                  </div>
                @endif
                @if (isset($auction->get->business_name))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Business Name: <span class="removeBold"> {{ @$auction->get->business_name }}</span> 
                  </div>
                @endif
                @if (isset($auction->get->year_established))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Year Established: <span class="removeBold"> {{ @$auction->get->year_established }}</span> 
                  </div>
                @endif
                @if (isset($auction->get->licenses))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Licenses: <span class="removeBold"> {{ @$auction->get->licenses != "Other" ? $auction->get->licenses : ($auction->get->custom_licenses ?? '')  }}</span> 
                  </div>
                @endif
                <hr>
              @endif
              {{-- Real Estate Included --}}
              {{-- Interior Features start --}}
              <h4>Interior Features:</h4>
              @if (@$auction->get->bedrooms != null)
              <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                Bedrooms:
                <span
                  class="removeBold">{{ @$auction->get->bedrooms != 'Other' ? @$auction->get->bedrooms : @$auction->get->custom_bedrooms }}</span>
              </div>
              @endif
              @if (@$auction->get->bathrooms != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Bathrooms:
                  @if ($auction->get->bathrooms != null)
                    <span class="removeBold">{{ @$auction->get->bathrooms != 'Other' ? @$auction->get->bathrooms : @$auction->get->custom_bathrooms }}</span>
                  {{-- @elseif ($auction->get->bathroomsCom != null)
                    <span class="removeBold">{{ @$auction->get->bathroomsCom != 'Other' ? @$auction->get->bathroomsCom : @$auction->get->custom_bathrooms_com }}</span> --}}
                  @endif
                </div>
              @endif
              @if (@$auction->get->heated_sqft != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Heated sqft: 
                  @if ($auction->get->heated_sqft !== null)
                    <span class="removeBold"> {{ @$auction->get->heated_sqft }}</span> 
                  {{-- @elseif ($auction->get->heated_sqft_com !== null)
                    <span class="removeBold"> {{ @$auction->get->heated_sqft_com }}</span>  --}}
                  @endif
                </div>
              @endif
              @if (@$auction->get->total_sqft != null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Total Sqft: 
                    @if($auction->get->total_sqft != null)
                      <span class="removeBold"> {{ @$auction->get->total_sqft }}</span> 
                    {{-- @elseif ($auction->get->total_sqft_com != null)
                      <span class="removeBold"> {{ @$auction->get->total_sqft_com }}</span>  --}}
                    @endif
                  </div>
              @endif
              @if (@$auction->get->heated_source != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                  Heated Sqft Source: 
                  @if ($auction->get->heated_source != null)
                    <span class="removeBold"> {{ @$auction->get->heated_source }}</span> 
                  {{-- @elseif ($auction->get->heated_source_com != null)
                    <span class="removeBold"> {{ @$auction->get->heated_source_com }}</span>  --}}
                  @endif
                </div>
              @endif
              @if (isset($auction->get->appliances) && @$auction->get->appliances != null && @$auction->get->appliances != 'null')
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Appliances:
                  @if (gettype(@$auction->get->appliances) == 'array')
                    @foreach (@$auction->get->appliances as $item)
                      <span class="badge bg-secondary removeBold">
                        @if($item !='Other')
                          {{ $item }}
                        @endif
                        @if($item =='Other')
                          {{ @$auction->get->otherAppliances ?:  $auction->get->otherAppliancesCom }}
                        @endif
                      </span>
                    @endforeach
                  @endif
                </div>
              @endif
              @if (@$auction->get->fireplace != null)
              <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                Fireplace:
                <span
                  class="removeBold">{{ $auction->get->fireplace }}</span>
              </div>
              @endif
              @if (@$auction->get->has_furnishing != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                  Furnishings Included: 
                  @if (($auction->get->has_furnishing != 'Yes' || $auction->get->has_furnishing != 'Optional') || ($auction->get->has_furnishing_com != 'Yes' || $auction->get->has_furnishing_com != 'Optional'))
                    <span class="removeBold"> {{ @$auction->get->has_furnishing ?: $auction->get->has_furnishing_com }}</span> 
                  @else
                    <i class="fa-regular fa-check-square"></i> 
                      What furnishings are included in the purchase? 
                      <span class="removeBold"> {{ @$auction->get->furnishings_include ?: $auction->get->furnishings_include_com }}</span> 
                  @endif
                  @if ($auction->get->has_additional_fees != null || $auction->get->has_additional_fees_com != null)
                    <br>
                    <i class="fa-regular fa-check-square"></i> 
                    Additional Fees or Included in the Purchase Price?
                    <span class="removeBold"> {{ @$auction->get->has_additional_fees ?: $auction->get->has_additional_fees_com }}</span> 
                    @if (($auction->get->has_additional_fees != 'Additional Fees' || $auction->get->has_additional_fees_com != 'Additional Fees') && ($auction->get->listed_furniture_price != null || $auction->get->listed_furniture_price_com != null))
                      <br>
                      <i class="fa-regular fa-check-square"></i> 
                      How much is the furniture listed?
                      <span class="removeBold"> {{ $auction->get->listed_furniture_price ?: $auction->get->listed_furniture_price_com }}</span> 
                    @endif
                  @endif
                </div>
              @endif
              @if (@$auction->get->number_of_buildings != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                  Number of Floors in the Property: 
                  <span class="removeBold"> {{ @$auction->get->number_of_buildings ?: $auction->get->number_of_buildings_com }}</span> 
                </div>
              @endif
              @if (@$auction->get->floors_in_unit != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Floor Number: 
                  <span class="removeBold"> {{ @$auction->get->floors_in_unit ?: $auction->get->floors_in_unit_com }}</span>
                </div>
              @endif
              @if (@$auction->get->total_floors != null )
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Number of Floors in the Entire Building: 
                  <span class="removeBold"> {{ @$auction->get->total_floors ?: $auction->get->total_floors_com }}</span>
                </div>
              @endif
              @if (@$auction->get->building_elevator != null )
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Building Elevator: 
                  <span class="removeBold"> {{ @$auction->get->building_elevator ?: $auction->get->building_elevator_com }}</span>
                </div>
              @endif
              @if (@$auction->get->interior_features != null && @$auction->get->interior_features != 'null')
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Interior Features:
                  @if (gettype(@$auction->get->interior_features) == 'array')
                    @foreach (@$auction->get->interior_features as $item)
                      @if($item != 'Other')
                        <span class="badge bg-secondary removeBold"> {{ $item }}</span>
                      @endif
                      @if($item == 'Other')
                        <span class="badge bg-secondary removeBold"> {{ @$auction->get->otherInterior }}</span>
                      @endif
                    @endforeach
                  @endif
                </div>
              @endif
              @if (isset($auction->get->additionalRooms))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                  Additional Rooms: 
                  @if (is_array($auction->get->additionalRooms) && count($auction->get->additionalRooms) > 0)
                    @foreach (@$auction->get->additionalRooms as $item)
                      <span class="removeBold badge bg-secondary"> {{ $item }}</span> 
                    @endforeach
                  @else
                    <span class="removeBold badge bg-secondary"> {{  $auction->get->additionalRooms }}</span>
                  @endif
                </div>
              @endif
              @if (@$auction->get->floor_covering != null && @$auction->get->floor_covering != 'null')
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Floor Covering:
                  @if (gettype(@$auction->get->floor_covering) == 'array')
                    @foreach (@$auction->get->floor_covering as $item)
                      @if($item != 'Other')
                      <span class="badge bg-secondary removeBold"> {{ $item }}</span>
                      @endif
                      @if($item == 'Other')
                      <span class="badge bg-secondary removeBold"> {{ @$auction->get->otherFloorCovering ?: $auction->get->otherFloorCoveringCom }}</span>
                      @endif
                    @endforeach
                  @endif
                </div>
              @endif
              @if (@$auction->get->utilities != null && @$auction->get->utilities != 'null')
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Utilities:
                  @if (gettype(@$auction->get->utilities) == 'array')
                    @foreach (@$auction->get->utilities as $item)
                      @if($item != 'Other')
                      <span class="badge bg-secondary removeBold"> {{ $item }}</span>
                      @endif
                      @if($item == 'Other')
                      <span class="badge bg-secondary removeBold"> {{ @$auction->get->otherUtilitise ?: $auction->get->otherUtilitiseCom ?: $auction->get->otherUtilitiseVac }}</span>
                      @endif
                    @endforeach
                  @endif
                </div>
              @endif
              @if (@$auction->get->water != null && is_array($auction->get->water))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Water: 
                  @foreach ($auction->get->water as $item)
                    <span class="removeBold">
                      {{$item != 'Other'? $item :($auction->get->otherWater ?: $auction->get->otherWaterCom ?: $auction->get->otherWaterVac)}}
                    </span> 
                  @endforeach
                </div>
              @endif
              @if (isset($auction->get->sewer) && is_array($auction->get->sewer) && count($auction->get->sewer) > 0)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Sewer: 
                  @foreach ($auction->get->sewer as $item)
                    <span class="removeBold">
                      {{$item != 'Other'? $item :($auction->get->otherSewer ?: $auction->get->otherSewerCom ?: $auction->get->otherSewerVac)}}
                    </span> 
                  @endforeach
                </div>
              @endif
              {{-- @if (@$auction->get->air_conditioning != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                  Air Conditioning: 
                  <span class="removeBold">{{(@$auction->get->air_conditioning !='Other' || $auction->get->air_conditioning_com !='Other' || $auction->get->air_conditioning_vac !='Other') ? (@$auction->get->air_conditioning || $auction->get->air_conditioning_com || $auction->get->air_conditioning_vac) : ($auction->get->otherAirCondition ?: $auction->get->otherAirConditionCom ?: $auction->get->otherAirConditionVac)}}</span> 
                </div>
              @endif --}}
              @if (isset($auction->get->heating_and_fuel) && is_array($auction->get->heating_and_fuel))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Heating and Fuel: 
                  @foreach ($auction->get->heating_and_fuel as $item)
                    <span class="removeBold">{{$item !='Other' ? $item : ($auction->get->otherHeatingFuel ?? $auction->get->otherHeatingFuelCom ?? '')}}</span> 
                  @endforeach
                </div>
              @endif
              {{-- Interior Features end --}}
              {{-- Room Details start --}}
              <hr>
              <h4>Room Details:</h4>
              @if (isset($auction->get->approximate_room_dimensions))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Approximate Room Dimensions (Width x Length): 
                  <span class="removeBold"> {{ $auction->get->approximate_room_dimensions }}</span>
                </div>
              @endif
              @if (isset($auction->get->room_type) && $auction->get->room_type != 'null')
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Room Type:
                  @if (gettype(@$auction->get->room_type) == 'array')
                    @foreach (@$auction->get->room_type as $item)
                      <span class="badge bg-secondary removeBold"> {{ $item }}</span>
                    @endforeach
                  @endif
                </div>
              @endif
              @if (isset($auction->get->room_level) && @$auction->get->room_level != 'null')
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Room Level:
                  @if (gettype(@$auction->get->room_level) == 'array')
                    @foreach (@$auction->get->room_level as $item)
                      <span class="badge bg-secondary removeBold"> {{ $item }}</span>
                    @endforeach
                  @endif
                </div>
              @endif
              @if (isset($auction->get->bed_room_closest_type) && @$auction->get->bed_room_closest_type != 'null')
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Closet Type:
                  @if (gettype(@$auction->get->bed_room_closest_type) == 'array')
                    @foreach (@$auction->get->bed_room_closest_type as $item)
                      <span class="badge bg-secondary removeBold"> {{ $item }}</span>
                    @endforeach
                  @endif
                </div>
              @endif
              @if (isset($auction->get->room_primary_floor_covering) && @$auction->get->room_primary_floor_covering != 'null')
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Room Primary Covering:
                  @if (gettype(@$auction->get->room_primary_floor_covering) == 'array')
                    @foreach (@$auction->get->room_primary_floor_covering as $item)
                      <span class="badge bg-secondary removeBold"> {{ $item }}</span>
                    @endforeach
                  @endif
                </div>
              @endif
              @if (isset($auction->get->room_feature) && @$auction->get->room_feature != 'null')
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Room Features:
                  @if (gettype(@$auction->get->room_feature) == 'array')
                    @foreach (@$auction->get->room_feature as $item)
                      @if($item != 'Other')
                        <span class="badge bg-secondary removeBold"> {{ $item }}</span>
                      @endif
                      @if($item == 'Other')
                        <span class="badge bg-secondary removeBold"> {{ @$auction->get->custom_room_features }}</span>
                      @endif
                    @endforeach
                  @endif
                </div>
              @endif
              {{-- Room Details end --}}
              <hr>
              {{-- Exterior Features start --}}
              <h4>Exterior Features</h4>
              @if (isset($auction->get->front_exposure) || isset($auction->get->front_exposure_com) || isset($auction->get->front_exposure_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Front Exposure: 
                    <span class="removeBold"> {{ $auction->get->front_exposure ?? $auction->get->front_exposure_com ?? $auction->get->front_exposure_vac ?? '' }}</span>
                </div>
              @endif
              @if (isset($auction->get->foundation) && @$auction->get->foundation != 'null')
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Foundation:
                  @if (gettype(@$auction->get->foundation) == 'array')
                    @foreach (@$auction->get->foundation as $item)
                      @if($item != 'Other')
                      <span class="badge bg-secondary removeBold"> {{ $item }}</span>
                      @endif
                      @if($item == 'Other')
                      <span class="badge bg-secondary removeBold"> {{ @$auction->get->otherFoundation ?? $auction->get->otherFoundationCom ?? '' }}</span>
                      @endif
                    @endforeach
                  @endif
                </div>
              @endif
              @if (isset($auction->get->exterior_construction) && @$auction->get->exterior_construction != 'null')
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Exterior Construction:
                  @if (gettype(@$auction->get->exterior_construction) == 'array')
                    @foreach (@$auction->get->exterior_construction as $item)
                      @if($item != 'Other')
                      <span class="badge bg-secondary removeBold"> {{ $item }}</span>
                      @endif
                      @if($item == 'Other')
                      <span class="badge bg-secondary removeBold"> {{ @$auction->get->otherConstruction ?? $auction->get->otherConstructionCom ?? '' }}</span>
                      @endif
                    @endforeach
                  @endif
                </div>
              @endif
              @if (isset($auction->get->exterior_feature) && @$auction->get->exterior_feature != 'null')
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Exterior Features:
                  @if (gettype(@$auction->get->exterior_feature) == 'array')
                    @foreach (@$auction->get->exterior_feature as $item)
                      @if($item != 'Other')
                      <span class="badge bg-secondary removeBold"> {{ $item }}</span>
                      @endif
                      @if($item == 'Other')
                      <span class="badge bg-secondary removeBold"> {{ @$auction->get->otherExterior }}</span>
                      @endif
                    @endforeach
                  @endif
                </div>
              @endif
              @if (isset($auction->get->lot_features) && @$auction->get->lot_features != 'null')
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Lot Features:
                  @if (gettype(@$auction->get->lot_features) == 'array')
                    @foreach (@$auction->get->lot_features as $item)
                      @if($item != 'Other')
                      <span class="badge bg-secondary removeBold"> {{ $item }}</span>
                      @endif
                      @if($item == 'Other')
                      <span class="badge bg-secondary removeBold"> {{ @$auction->get->otherLotFeature ?? $auction->get->otherLotFeatureCom ?? $auction->get->otherLotFeatureVac ?? '' }}</span>
                      @endif
                    @endforeach
                  @endif
                </div>
              @endif
              @if (isset($auction->get->otherStructureOpt) && @$auction->get->otherStructureOpt != 'null')
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Other Structures:
                  @if(@$auction->get->otherStructureOpt == 'Yes' && @$auction->get->otherStructureOpt != 'Other')
                    <span class="badge bg-secondary removeBold"> {{ @$auction->get->otherStruct }}</span>
                  @endif
                  @if(@$auction->get->otherStruct == 'Other')
                    <span class="badge bg-secondary removeBold"> {{ @$auction->get->otherStructure  }}</span>
                  @endif
                </div>
              @endif
              @if (isset($auction->get->roof) && $auction->get->roof != 'null')
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Roof:
                  @if (gettype(@$auction->get->roof) == 'array')
                    @foreach (@$auction->get->roof as $item)
                      @if($item != 'Other')
                      <span class="badge bg-secondary removeBold"> {{ $item }}</span>
                      @endif
                      @if($item == 'Other')
                      <span class="badge bg-secondary removeBold"> {{ @$auction->get->otherRoof ?? $auction->get->otherRoofCom ?? '' }}</span>
                      @endif
                    @endforeach
                  @endif
                </div>
              @endif
              @if (isset($auction->get->road_surface_type) && @$auction->get->road_surface_type != 'null')
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Road Surface Type:
                  @if (gettype(@$auction->get->road_surface_type) == 'array')
                    @foreach (@$auction->get->road_surface_type as $item)
                      @if($item != 'Other')
                      <span class="badge bg-secondary removeBold"> {{ $item }}</span>
                      @endif
                      @if($item == 'Other')
                      <span class="badge bg-secondary removeBold"> {{ @$auction->get->otherSurface ?? $auction->get->otherSurfaceCom ?? $auction->get->otherSurfaceVac ?? '' }}</span>
                      @endif
                    @endforeach
                  @endif
                </div>
              @endif
              @if (isset($auction->get->garage))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Garage: <span class="removeBold"> {{ @$auction->get->garage }}</span> 
                </div>
              @endif
              @if (isset($auction->get->garage_spaces))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Garage Spaces: <span class="removeBold"> {{ @$auction->get->garage_spaces }}</span> 
                </div>
              @endif
              @if (isset($auction->get->carport))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Carport: <span class="removeBold"> {{ @$auction->get->carport }}</span> 
                </div>
              @endif
              @if (isset($auction->get->carport_spaces))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Carport Spaces: <span class="removeBold"> {{ @$auction->get->carport_spaces }}</span> 
                </div>
              @endif
              @if (isset($auction->get->pool))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Pool: <span class="removeBold">{{@$auction->get->pool != 'Yes'? @$auction->get->pool : ''}}
                    @if(@$auction->get->pool == 'Yes')
                      {{@$auction->get->poolOpt}}
                    @endif
                  </span> 
                </div>
              @endif
              @if (isset($auction->get->pool_type))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Pool: <span class="removeBold"> {{ @$auction->get->pool_type }}</span> 
                </div>
              @endif
              @if (isset($auction->get->view) && is_array($auction->get->view) && count($auction->get->view) > 0)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  View:
                    @foreach (@$auction->get->view as $item)
                      <span class="badge bg-secondary removeBold"> {{ $item !='Other'? $item : $auction->get->otherView}}</span>
                    @endforeach
                </div>
              @endif
              {{-- Exterior Features end --}}
              <hr>
              {{-- Water and Dock Information start --}}
              <h4>Water and Dock Information:</h4>
              @if (isset($auction->get->has_water_access) || isset($auction->get->has_water_access_com) || isset($auction->get->has_water_access_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Water View: 
                  <span class="removeBold">{{@$auction->get->has_water_access ?? $auction->get->has_water_access_com ?? $auction->get->has_water_access_vac ?? ''}}
                  @if(@$auction->get->has_water_access == 'Yes' || $auction->get->has_water_access_com == 'Yes' || $auction->get->has_water_access_vac == 'Yes')
                    @if (gettype(@$auction->get->water_access) == 'array')
                      @foreach (@$auction->get->water_access as $item)
                        <span class="badge bg-secondary removeBold"> {{ $item }}</span>
                      @endforeach
                    @endif
                  @endif
                  </span> 
                </div>
              @endif
              @if (isset($auction->get->has_water_view) || isset($auction->get->has_water_view_com) || isset($auction->get->has_water_view_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Water View: 
                  <span class="removeBold">{{@$auction->get->has_water_view ?? $auction->get->has_water_view_com ?? $auction->get->has_water_view_vac ?? ''}}
                  @if(@$auction->get->has_water_view == 'Yes' || $auction->get->has_water_view_com == 'Yes' || $auction->get->has_water_view_vac == 'Yes')
                    @if (gettype(@$auction->get->water_view) == 'array')
                      @foreach (@$auction->get->water_view as $item)
                        <span class="badge bg-secondary removeBold"> {{ $item }}</span>
                      @endforeach
                    @endif
                  @endif
                  </span> 
                </div>
              @endif
              @if (isset($auction->get->has_water_extra) || isset($auction->get->has_water_extra_com) || isset($auction->get->has_water_extra_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Water Extra: 
                  <span class="removeBold">{{@$auction->get->has_water_extra ?? $auction->get->has_water_extra_com ?? $auction->get->has_water_extra_vac ?? ''}}
                  @if(@$auction->get->has_water_extra == 'Yes' || $auction->get->has_water_extra_com == 'Yes' || $auction->get->has_water_extra_vac == 'Yes')
                    @if (gettype(@$auction->get->water_extra) == 'array')
                      @foreach (@$auction->get->water_extra as $item)
                        <span class="badge bg-secondary removeBold"> {{ $item }}</span>
                      @endforeach
                    @endif
                  @endif
                  </span> 
                </div>
              @endif
              @if (isset($auction->get->has_water_fontage) || isset($auction->get->has_water_fontage_com) || isset($auction->get->has_water_fontage_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Water Frontage: 
                  <span class="removeBold">{{@$auction->get->has_water_fontage ?? $auction->get->has_water_fontage_com ?? $auction->get->has_water_fontage_vac ?? ''}}
                  @if(@$auction->get->has_water_fontage == 'Yes' || $auction->get->has_water_fontage_com == 'Yes' || $auction->get->has_water_fontage_vac == 'Yes')
                    @if (gettype(@$auction->get->water_fontage) == 'array')
                      @foreach (@$auction->get->water_fontage as $item)
                        <span class="badge bg-secondary removeBold"> {{ $item }}</span>
                      @endforeach
                    @endif
                  @endif
                  </span> 
                </div>
              @endif
              @if (isset($auction->get->has_dock) || isset($auction->get->has_dock_com) || isset($auction->get->has_dock_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Dock: 
                  <span class="removeBold">{{@$auction->get->has_dock ?? $auction->get->has_dock_com ?? $auction->get->has_dock_vac ?? ''}}
                  @if(@$auction->get->has_dock == 'Yes' || $auction->get->has_dock_com == 'Yes' || $auction->get->has_dock_vac == 'Yes')
                    @if (gettype(@$auction->get->dock) == 'array')
                      @foreach (@$auction->get->dock as $item)
                        <span class="badge bg-secondary removeBold"> {{ $item }}</span>
                      @endforeach
                    @endif
                  @endif
                  </span> 
                </div>
              @endif
              {{-- Water and Dock Information end --}}
              <hr>
              {{-- Land and Tax Information start --}}
              <h4>Land and Tax Information:</h4>
              @if (isset($auction->get->tax_id) || isset($auction->get->tax_id_com) || isset($auction->get->tax_id_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Tax ID (Parcel Number):<span class="removeBold"> {{ @$auction->get->tax_id ?? $auction->get->tax_id_com ?? $auction->get->tax_id_vac ?? '' }}</span>
                </div>
              @endif
              @if (isset($auction->get->tax_year) || isset($auction->get->tax_year_com) || isset($auction->get->tax_year_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Tax Year:<span class="removeBold"> {{ @$auction->get->tax_year ?? $auction->get->tax_year_com ?? $auction->get->tax_year_vac ?? '' }}</span>
                </div>
              @endif
              @if (isset($auction->get->taxes_annual_amount) || isset($auction->get->taxes_annual_amount_com) || isset($auction->get->taxes_annual_amount_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Taxes (Annual Amount): <span class="removeBold"> {{ @$auction->get->taxes_annual_amount ?? $auction->get->taxes_annual_amount_com ?? $auction->get->taxes_annual_amount_vac ?? '' }}</span>
                </div>
              @endif
              @if (isset($auction->get->additionalParcels) || isset($auction->get->additionalParcelsCom) || isset($auction->get->additionalParcelsVac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Additional Parcels: <span class="removeBold"> {{ @$auction->get->additionalParcels ?? $auction->get->additionalParcelsCom ?? $auction->get->additionalParcelsVac ?? '' }}</span>
                </div>
              @endif
              @if (isset($auction->get->total_number_of_parcels) || isset($auction->get->total_number_of_parcels_com) || isset($auction->get->total_number_of_parcels_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Total Number of Parcels: <span class="removeBold"> {{ @$auction->get->total_number_of_parcels ?? $auction->get->total_number_of_parcels_com ?? $auction->get->total_number_of_parcels_vac ?? '' }}</span>
                </div>
              @endif
              @if (isset($auction->get->additional_tax_id) || isset($auction->get->additional_tax_id_com) || isset($auction->get->additional_tax_id_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Additional Tax ID’s: <span class="removeBold"> {{ @$auction->get->additional_tax_id ?? $auction->get->additional_tax_id_com ?? $auction->get->additional_tax_id_vac ?? '' }}</span>
                </div>
              @endif
              @if (isset($auction->get->year_built) || isset($auction->get->year_built_com))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Year Built: <span class="removeBold"> {{ @$auction->get->year_built ?? $auction->get->year_built_com ?? '' }}</span>
                </div>
              @endif
              @if (isset($auction->get->zoning) || isset($auction->get->zoning_com) || isset($auction->get->zoning_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Zoning: <span class="removeBold"> {{ @$auction->get->zoning ?? $auction->get->zoning_com ?? $auction->get->zoning_vac ?? '' }}</span>
                </div>
              @endif
              @if (isset($auction->get->legal_description) || isset($auction->get->legal_description_com) || isset($auction->get->legal_description_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Legal Description: <span class="removeBold"> {{ @$auction->get->legal_description ?? $auction->get->legal_description_com ?? $auction->get->legal_description_vac ?? '' }}</span>
                </div>
              @endif
              @if (isset($auction->get->legal_subdivison_name) || isset($auction->get->legal_subdivison_name_com) || isset($auction->get->legal_subdivison_name_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Legal Subdivision Name: <span class="removeBold"> {{ @$auction->get->legal_subdivison_name ?? $auction->get->legal_subdivison_name_com ?? $auction->get->legal_subdivison_name_vac ?? '' }}</span>
                </div>
              @endif
              @if (isset($auction->get->total_aceage) || isset($auction->get->total_aceage_com) || isset($auction->get->total_aceage_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                  Total Acreage: <span class="removeBold"> {{ @$auction->get->total_aceage ?? $auction->get->total_aceage_com ?? $auction->get->total_aceage_vac ?? '' }}</span> 
                </div>
              @endif
              @if (isset($auction->get->flood_zone_code) || isset($auction->get->flood_zone_code_com) || isset($auction->get->flood_zone_code_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Flood Zone Code: <span class="removeBold"> {{ $auction->get->flood_zone_code ?? $auction->get->flood_zone_code_com ?? $auction->get->flood_zone_code_vac ?? '' }}</span>
                </div>
              @endif
              @if (isset($auction->get->lot_size) || isset($auction->get->lot_size_com) || isset($auction->get->lot_size_vac))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Lot Size Square Footage: <span class="removeBold"> {{ @$auction->get->lot_size ?? $auction->get->lot_size_com ?? $auction->get->lot_size_vac ?? '' }}</span> 
                  </div>
              @endif
              @if (isset($auction->get->lot_size_acres) || isset($auction->get->lot_size_acres_com) || isset($auction->get->lot_size_acres_vac))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> 
                    Lot Size Acres: <span class="removeBold"> {{ @$auction->get->lot_size_acres ?? $auction->get->lot_size_acres_com ?? $auction->get->lot_size_acres_vac ?? '' }}</span> 
                  </div>
              @endif
              @if ($auction->get->property_type == 'Vacant Land')
                @if (isset($auction->get->lot_dimensions))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Lot Dimensions: <span class="removeBold"> {{ @$auction->get->lot_dimensions }}
                    </span>
                  </div>
                @endif
              @endif
              @if ($auction->get->property_type == 'Residential Property' || $auction->get->property_type == 'Income Opportunity')
                @if (isset($auction->get->has_homestead))
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                      Homestead: <span class="removeBold"> {{ @$auction->get->has_homestead }}
                      </span>
                    </div>
                @endif
              @endif  
              @if (isset($auction->get->has_cdd) || isset($auction->get->has_cdd_vac))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    CDD: <span class="removeBold"> {{ $auction->get->has_cdd ?? $auction->get->has_cdd_vac ?? '' }}
                    </span>
                  </div>
              @endif
              @if (isset($auction->get->annual_cdd_fee) || isset($auction->get->annual_cdd_fee_vac))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Annual CDD Fee: <span class="removeBold">${{ $auction->get->annual_cdd_fee ?? $auction->get->annual_cdd_fee_vac ?? '' }}
                    </span>
                  </div>
              @endif
              @if ($auction->get->property_type == 'Residential Property' || $auction->get->property_type == 'Business Opportunity')
                @if (isset($auction->get->has_land_lease) || isset($auction->get->has_land_lease_vac))
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                      Land Lease: <span class="removeBold">${{ $auction->get->has_land_lease ?? $auction->get->has_land_lease_vac ?? '' }}
                      </span>
                    </div>
                @endif
                @if (isset($auction->get->has_cdd) || isset($auction->get->has_cdd_vac))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Annual Land Lease Fee: <span class="removeBold">${{ $auction->get->has_cdd ?? $auction->get->has_cdd_vac ?? '' }}
                    </span>
                  </div>
                @endif
              @endif  
              {{-- Land and Tax Information end --}}
              <hr>
              {{-- Ownership, Leasing Restrictions and Pets Information start --}}
              @if($auction->get->property_type == 'Commercial Property' || $auction->get->property_type == 'Business Opportunity')
                <h4>Ownership and Occupant Type:</h4>
              @elseif($auction->get->property_type == 'Vacant Land')  
                <h4>Ownership Information:</h4>
              @else
                <h4>Ownership, Leasing Restrictions and Pets Information</h4>
              @endif
              
              @if (isset($auction->get->ownership) || isset($auction->get->ownership_com) || isset($auction->get->ownership_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Ownership: <span class="removeBold">{{ $auction->get->ownership ?? $auction->get->ownership_com ?? $auction->get->ownership_vac ?? '' }}
                  </span>
                </div>
              @endif
              @if($auction->get->property_type != 'Vacant Land')
                @if (isset($auction->get->occupant_type) || isset($auction->get->occupant_type_com))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Occupant Type: <span class="removeBold">{{ $auction->get->occupant_type ?? $auction->get->occupant_type_com ?? '' }}
                    </span>
                  </div>
                @endif
                @if (isset($auction->get->exiting_lease_or_tenant) || isset($auction->get->exiting_lease_or_tenant_com))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Existing Tenant or Lease: <span class="removeBold">{{ $auction->get->exiting_lease_or_tenant ?? $auction->get->exiting_lease_or_tenant_com ?? '' }}
                    </span>
                  </div>
                @endif
                @if (isset($auction->get->monthToMonth) || isset($auction->get->monthToMonth_com))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Required Notice Period for Tenant to Vacate the Property: <span class="removeBold">{{ $auction->get->monthToMonth ?? $auction->get->monthToMonth_com ?? '' }}
                    </span>
                  </div>
                @endif
                @if (isset($auction->get->monthly_rental_ammount) || isset($auction->get->monthly_rental_ammount_com))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Monthly Rental Income: <span class="removeBold">{{ $auction->get->monthly_rental_ammount ?? $auction->get->monthly_rental_ammount_com ?? '' }}
                    </span>
                  </div>
                @endif
                @if ($auction->get->property_type == 'Residential Property' || $auction->get->property_type == 'Income Property')
                  @if (isset($auction->get->days_notice_to_terminate) || isset($auction->get->days_notice_to_terminate_com))
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                      Days Notice to Tenant if not Renewing: <span class="removeBold">{{ $auction->get->days_notice_to_terminate ?? $auction->get->days_notice_to_terminate_com ?? '' }}
                      </span>
                    </div>
                  @endif
                  @if (isset($auction->get->end_of_lease_date) || isset($auction->get->end_of_lease_date_com))
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                      End Date of Lease: <span class="removeBold">{{ $auction->get->end_of_lease_date ?? $auction->get->end_of_lease_date_com ?? '' }}
                      </span>
                    </div>
                  @endif
                  @if (isset($auction->get->has_leasing))
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                      Can the property be leased? <span class="removeBold">{{ $auction->get->has_leasing }}
                      </span>
                    </div>
                  @endif
                  @if (isset($auction->get->has_lease_restriction))
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                      Lease Restrictions: <span class="removeBold">{{ $auction->get->has_lease_restriction }}
                      </span>
                    </div>
                  @endif
                  @if (isset($auction->get->has_lease_restriction))
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                      Lease Restrictions: <span class="removeBold">{{ $auction->get->has_lease_restriction }}
                      </span>
                    </div>
                  @endif
                  @if (isset($auction->get->association_approval_required))
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                      Association Approval Required: <span class="removeBold">{{ $auction->get->association_approval_required }}
                      </span>
                    </div>
                  @endif
                  @if (isset($auction->get->minimum_lease_period))
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                      Minimum Lease Period: <span class="removeBold">{{ $auction->get->minimum_lease_period }}
                      </span>
                    </div>
                  @endif
                  @if (isset($auction->get->minimum_lease_per_year))
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                      Maximum Lease Times Per Year: <span class="removeBold">{{ $auction->get->minimum_lease_per_year }}
                      </span>
                    </div>
                  @endif
                  @if (isset($auction->get->years_of_ownership))
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                      Years of Ownership Prior to Leasing Required: <span class="removeBold">{{ $auction->get->years_of_ownership }}
                      </span>
                    </div>
                  @endif
                  @if (isset($auction->get->number_of_ownership_prior_lease))
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                      Number of Ownership Years Prior to Leasing: <span class="removeBold">{{ $auction->get->number_of_ownership_prior_lease }}
                      </span>
                    </div>
                  @endif
                  @if (isset($auction->get->ptes_Allowed) || isset($auction->get->ptes_Allowed_vac))
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                      Pets Allowed: <span class="removeBold">{{ $auction->get->ptes_Allowed ?? $auction->get->ptes_Allowed_vac ?? '' }}
                      </span>
                    </div>
                  @endif
                  @if (isset($auction->get->acceptablePet))
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                      Acceptable Pet Types: <span class="removeBold">{{ $auction->get->acceptablePet }}
                      </span>
                    </div>
                  @endif
                  @if (isset($auction->get->total_pets_allowed))
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                      Number of Pets Allowed: <span class="removeBold">{{ $auction->get->total_pets_allowed != 'Other' ? $auction->get->total_pets_allowed : $auction->get->custom_pets_allowed }}
                      </span>
                    </div>
                  @endif
                  @if (isset($auction->get->max_pet_weight))
                    <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                      Max Pet Weight: <span class="removeBold">{{ $auction->get->max_pet_weight }}
                      </span>
                    </div>
                  @endif
                @endif
              @endif
              {{-- Ownership, Leasing Restrictions and Pets Information end --}}
              <hr>
              {{-- Financial Information --}}
              @if ($auction->get->property_type == 'Commercial Property' || $auction->get->property_type == 'Business Opportunity')
                <h4>Financial Information:</h4>
                @if (isset($auction->get->operating_expenses))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Operating Expenses: 
                      <span class="removeBold">{{ $auction->get->operating_expenses }}</span>
                  </div>
                @endif  
                @if (isset($auction->get->net_operating_income))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Net Operating Income: 
                      <span class="removeBold">{{ $auction->get->net_operating_income }}</span>
                  </div>
                @endif 
                @if (isset($auction->get->net_operating_income_type))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Net Operating Income Type:  
                      <span class="removeBold">{{ $auction->get->net_operating_income_type }}</span>
                  </div>
                @endif 
                @if (isset($auction->get->annual_expenses))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Annual Expense:  
                      <span class="removeBold">{{ $auction->get->annual_expenses }}</span>
                  </div>
                @endif  
                @if (isset($auction->get->annual_ttl_schedule_income))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Annual TTL Schedule Income:  
                      <span class="removeBold">{{ $auction->get->annual_ttl_schedule_income }}</span>
                  </div>
                @endif  
                @if (isset($auction->get->annual_income_type))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Annual Income Type:  
                      <span class="removeBold">{{ $auction->get->annual_income_type }}</span>
                  </div>
                @endif  
                @if (isset($auction->get->saleInclude) && is_array($auction->get->saleInclude))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Sale Includes:  
                      @foreach ($auction->get->saleInclude as $item)
                        <span class="removeBold">{{ $item != 'Other' ? $item : $auction->get->otherSale }}</span>
                      @endforeach
                  </div>
                @endif     
                @if (isset($auction->get->number_of_tenants))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Number of Tenants:  
                      <span class="removeBold">{{ $auction->get->number_of_tenants }}</span>
                  </div>
                @endif    
                <hr>
              @endif
              {{-- Financial Information --}}
              {{-- Space --}}
              @if ($auction->get->property_type == 'Commercial Property' || $auction->get->property_type == 'Business Opportunity')
                <h4>Space:</h4>
                @if (isset($auction->get->class_of_space))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Class of Space:  
                      <span class="removeBold">{{ $auction->get->class_of_space }}</span>
                  </div>
                @endif 
                @if (isset($auction->get->sale_include))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Space Type: 
                      <span class="removeBold">{{ $auction->get->sale_include }}</span>
                  </div>
                @endif 
                @if (isset($auction->get->number_of_hotel))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    # of Hotel/Motel Rooms: 
                      <span class="removeBold">{{ $auction->get->number_of_hotel }}</span>
                  </div>
                @endif
                @if (isset($auction->get->number_of_conference))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    # of Conference/Meeting Rooms: 
                      <span class="removeBold">{{ $auction->get->number_of_conference }}</span>
                  </div>
                @endif
                @if (isset($auction->get->number_of_restrooms))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    # of Restrooms: 
                      <span class="removeBold">{{ $auction->get->number_of_restrooms }}</span>
                  </div>
                @endif
                @if (isset($auction->get->number_of_bays_high))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    # of Bays (Dock Hight): 
                      <span class="removeBold">{{ $auction->get->number_of_bays_high }}</span>
                  </div>
                @endif
                @if (isset($auction->get->number_of_offices))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    # of Offices: 
                      <span class="removeBold">{{ $auction->get->number_of_offices }}</span>
                  </div>
                @endif
                <hr>
              @endif
              {{-- Space --}}
              {{-- HOA, Condo Association and/or Master Association Information start --}}
              @if ($auction->get->property_type == 'Residential Property' || $auction->get->property_type == 'Income Property' || $auction->get->property_type == 'Vacant Land')
                <h4>HOA, Condo Association and/or Master Association Information</h4>
              @elseif ($auction->get->property_type == 'Commercial Property' || $auction->get->property_type == 'Business Opportunity')
                <h4>Condo Environment:</h4>
              @endif
              
              @if ($auction->get->property_type == 'Residential Property' || $auction->get->property_type == 'Income Property')
                @if (isset($auction->get->has_hoa) || isset($auction->get->has_hoa_vac))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Does the property have an HOA, condo association, master association, and/or community fee? 
                      <span class="removeBold">{{ $auction->get->has_hoa ?? $auction->get->has_hoa_vac ?? '' }}</span>
                  </div>
                @endif
                @if (isset($auction->get->community_feature))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Community Features:
                      @if (gettype(@$auction->get->community_feature) == 'array')
                        @foreach (@$auction->get->community_feature as $item)
                          <span class="badge bg-secondary removeBold">{{ $item }}</span>
                        @endforeach
                      @endif
                  </div>
                @endif
                @if (isset($auction->get->association_amenitie))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Association Amenities:
                      @if (gettype(@$auction->get->association_amenitie) == 'array')
                        @foreach (@$auction->get->association_amenitie as $item)
                          <span class="badge bg-secondary removeBold">{{ $item != 'Other' ? $item : ($auction->get->otherAssocAmenities ?? $auction->get->otherAssocAmenities_vac ?? '' ) }}</span>
                        @endforeach
                      @endif
                  </div>
                @endif
                @if (isset($auction->get->fee_include))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Fee Includes:
                      @if (gettype(@$auction->get->fee_include) == 'array')
                        @foreach (@$auction->get->fee_include as $item)
                          <span class="badge bg-secondary removeBold">{{ $item != 'Other' ? $item : ($auction->get->otherFeeInclude ?? $auction->get->otherFeeInclude_vac ?? '' ) }}</span>
                        @endforeach
                      @endif
                  </div>
                @endif
                @if (isset($auction->get->amenities_with_additional_fees) || isset($auction->get->amenities_with_additional_fees_vac))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Amenities with Additional Fees:
                      <span class="removeBold">{{ $auction->get->amenities_with_additional_fees ?? $auction->get->amenities_with_additional_fees_vac ?? '' }}</span>
                  </div>
                @endif
                @if (isset($auction->get->hoaFeeRequirements) || isset($auction->get->hoaFeeRequirements_vac))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    HOA Fee Requirement:
                      <span class="removeBold">{{ $auction->get->hoaFeeRequirements ?? $auction->get->hoaFeeRequirements_vac ?? '' }}</span>
                  </div>
                @endif
                @if (isset($auction->get->hoaFeeAmount) || isset($auction->get->hoaFeeAmount_vac))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    HOA Fee:
                      <span class="removeBold">{{ $auction->get->hoaFeeAmount ?? $auction->get->hoaFeeAmount_vac ?? '' }}</span>
                  </div>
                @endif
                @if (isset($auction->get->paymentSchedules) || isset($auction->get->paymentSchedules_vac))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    HOA Payment Schedule:
                      <span class="removeBold">{{ $auction->get->paymentSchedules ?? $auction->get->paymentSchedules_vac ?? '' }}</span>
                  </div>
                @endif
              @endif
              @if (isset($auction->get->condoFeeAmount) || isset($auction->get->condoFeeAmount_com) || isset($auction->get->condoFeeAmount_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Condo Fee:
                    <span class="removeBold">{{ $auction->get->condoFeeAmount ?? $auction->get->condoFeeAmount_com ?? $auction->get->condoFeeAmount_vac ?? '' }}</span>
                </div>
              @endif
              @if($auction->get->property_type == 'Commercial Property' || $auction->get->property_type == 'Business Opportunity')
                @if (isset($auction->get->condo_fee_terms))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Condo Fee Term:
                      <span class="removeBold">{{ $auction->get->condo_fee_terms }}</span>
                  </div>
                @endif
              @endif 
              @if ($auction->get->property_type == 'Residential Property' || $auction->get->property_type == 'Income Property') 
                @if (isset($auction->get->condoPay) || isset($auction->get->condoPay_vac))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Condo Payment Schedule:
                      <span class="removeBold">{{ $auction->get->condoPay ?? $auction->get->condoPay_vac ?? '' }}</span>
                  </div>
                @endif
                @if (isset($auction->get->masterAssociationFeeAmount) || isset($auction->get->masterAssociationFeeAmount_vac))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Master Association Fee:
                      <span class="removeBold">{{ $auction->get->masterAssociationFeeAmount ?? $auction->get->masterAssociationFeeAmount_vac ?? '' }}</span>
                  </div>
                @endif
                @if (isset($auction->get->assocSchedule) || isset($auction->get->assocSchedule_vac))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Master Association Fee Schedule:
                      <span class="removeBold">{{ $auction->get->assocSchedule ?? $auction->get->assocSchedule_vac ?? '' }}</span>
                  </div>
                @endif
                @if (isset($auction->get->masterAssociationName) || isset($auction->get->masterAssociationName_vac))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Master Association Name:
                      <span class="removeBold">{{ $auction->get->masterAssociationName ?? $auction->get->masterAssociationName_vac ?? '' }}</span>
                  </div>
                @endif
                @if (isset($auction->get->masterAssociationContactPhone) || isset($auction->get->masterAssociationContactPhone_vac))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Master Association Contact Phone:
                      <span class="removeBold">{{ $auction->get->masterAssociationContactPhone ?? $auction->get->masterAssociationContactPhone_vac ?? '' }}</span>
                  </div>
                @endif
                @if (isset($auction->get->additionalFees) || isset($auction->get->additionalFees_vac))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Are there any additional fees?
                      <span class="removeBold">{{ $auction->get->additionalFees ?? $auction->get->additionalFees_vac ?? '' }}</span>
                  </div>
                @endif
                @if (isset($auction->get->hotherFeeAmount) || isset($auction->get->hotherFeeAmount_vac))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Other Fees Schedule:
                      <span class="removeBold">{{ $auction->get->hotherFeeAmount ?? $auction->get->hotherFeeAmount_vac ?? '' }}</span>
                  </div>
                @endif
              @endif
              @if (isset($auction->get->associationManagerContactName) || isset($auction->get->associationManagerContactName_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Association/Manager Contact Name:
                    <span class="removeBold">{{ $auction->get->associationManagerContactName ?? $auction->get->associationManagerContactName_vac ?? '' }}</span>
                </div>
              @endif
              @if (isset($auction->get->associationManagerContactEmail) || isset($auction->get->associationManagerContactEmail_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Association/Manager Contact Email:
                    <span class="removeBold">{{ $auction->get->associationManagerContactEmail ?? $auction->get->associationManagerContactEmail_vac ?? '' }}</span>
                </div>
              @endif
              @if (isset($auction->get->associationManagerContactPhone) || isset($auction->get->associationManagerContactPhone_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Association/Manager Contact Phone:
                    <span class="removeBold">{{ $auction->get->associationManagerContactPhone ?? $auction->get->associationManagerContactPhone_vac ?? '' }}</span>
                </div>
              @endif
              @if (isset($auction->get->associationManagerContactWebsite) || isset($auction->get->associationManagerContactWebsite_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Association/Manager Contact Website Address:
                    <span class="removeBold">{{ $auction->get->associationManagerContactWebsite ?? $auction->get->associationManagerContactWebsite_vac ?? '' }}</span>
                </div>
              @endif
              @if ($auction->get->property_type == 'Residential Property' || $auction->get->property_type == 'Income Property')
                @if (isset($auction->get->olderPersons) || isset($auction->get->olderPersons_vac))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Housing for Older Persons:
                      <span class="removeBold">{{ $auction->get->olderPersons ?? $auction->get->olderPersons_vac ?? '' }}</span>
                  </div>
                @endif
              @endif
              @if ($auction->get->property_type == 'Commercial Property' || $auction->get->property_type == 'Business Opportunity')
                @if (isset($auction->get->ptes_Allowed) || isset($auction->get->ptes_Allowed_vac))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Pets Allowed:
                      <span class="removeBold">{{ $auction->get->ptes_Allowed ?? $auction->get->ptes_Allowed_vac ?? '' }}</span>
                  </div>
                @endif
              @endif
              {{-- HOA, Condo Association and/or Master Association Information end --}}
              <hr>
              {{-- Green Features start --}}
              @if ($auction->get->property_type == 'Residential Property' || $auction->get->property_type == 'Income Property')
                <h4>Green Features</h4>
                @if (isset($auction->get->green_features))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Green Features:
                      <span class="removeBold">{{ $auction->get->green_features }}</span>
                  </div>
                @endif
                @if (isset($auction->get->building_verification))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Building Verification:
                      <span class="removeBold">{{ $auction->get->building_verification }}</span>
                  </div>
                @endif
                @if (isset($auction->get->green_status))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Status:
                      <span class="removeBold">{{ $auction->get->green_status }}</span>
                  </div>
                @endif
                @if (isset($auction->get->green_year))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Year:
                      <span class="removeBold">{{ $auction->get->green_year }}</span>
                  </div>
                @endif
                @if (isset($auction->get->green_version))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Version:
                      <span class="removeBold">{{ $auction->get->green_version }}</span>
                  </div>
                @endif
                @if (isset($auction->get->green_body))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Body:
                      <span class="removeBold">{{ $auction->get->green_body }}</span>
                  </div>
                @endif
                @if (isset($auction->get->green_metric))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Metric:
                      <span class="removeBold">{{ $auction->get->green_metric }}</span>
                  </div>
                @endif
                @if (isset($auction->get->green_rating))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Rating:
                      <span class="removeBold">{{ $auction->get->green_rating }}</span>
                  </div>
                @endif
                @if (isset($auction->get->green_source))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Source:
                      <span class="removeBold">{{ $auction->get->green_source }}</span>
                  </div>
                @endif
                @if (isset($auction->get->green_url))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Green Verification URL:
                      <span class="removeBold">{{ $auction->get->green_url }}</span>
                  </div>
                @endif
                @if (isset($auction->get->green_sustainability))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Green Sustainability:
                      <span class="removeBold">{{ $auction->get->green_sustainability }}</span>
                  </div>
                @endif
                @if (isset($auction->get->green_generation))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Green Energy Generation:
                      <span class="removeBold">{{ $auction->get->green_generation }}</span>
                  </div>
                @endif
                @if (isset($auction->get->green_water_features))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Green Water Features:
                      <span class="removeBold">{{ $auction->get->green_water_features }}</span>
                  </div>
                @endif
                @if (isset($auction->get->green_energy_features))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Green Energy Features:
                      <span class="removeBold">{{ $auction->get->green_energy_features }}</span>
                  </div>
                @endif
                @if (isset($auction->get->green_landscaping))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Green Landscaping:
                      <span class="removeBold">{{ $auction->get->green_landscaping }}</span>
                  </div>
                @endif
                @if (isset($auction->get->green_solar))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Solar Panel Ownership:
                      <span class="removeBold">{{ $auction->get->green_solar }}</span>
                  </div>
                @endif
                @if (isset($auction->get->green_disaster))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Disaster Mitigation:
                      <span class="removeBold">{{ $auction->get->green_disaster }}</span>
                  </div>
                @endif
                @if (isset($auction->get->green_air))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Indoor Air Quality:
                      <span class="removeBold">{{ $auction->get->green_air }}</span>
                  </div>
                @endif
              <hr>
              @endif
              {{-- Green Features end --}}
              {{-- Legal Disclaimers --}}
              @if (isset($auction->get->disclamer) || isset($auction->get->disclamer_com) || isset($auction->get->disclamer_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1">
                  <h4>Legal Disclaimers:</h4>
                    <span class="removeBold">{{ $auction->get->disclamer ?? $auction->get->disclamer_com ?? $auction->get->disclamer_vac ?? '' }}</span>
                </div>
                <hr>
              @endif
              {{-- Legal Disclaimers --}}
              {{-- Driving Directions --}}
              @if (isset($auction->get->driving_directions) || isset($auction->get->driving_directions_com) || isset($auction->get->driving_directions_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1">
                  <h4>Driving Directions:</h4>
                    <span class="removeBold">{{ $auction->get->driving_directions ?? $auction->get->driving_directions_com ?? $auction->get->driving_directions_vac ?? '' }}</span>
                </div>
                <hr>
              @endif
              {{-- Driving Directions --}}
              {{-- Buyer’s Agent Compensation --}}
              @if (isset($auction->get->compensation_amount) || isset($auction->get->compensation_amount_com) || isset($auction->get->compensation_amount_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1">
                  <h4>Buyer’s Agent Compensation:</h4>
                    <span class="removeBold">{{ $auction->get->compensation_amount ?? $auction->get->compensation_amount_com ?? $auction->get->compensation_amount_vac ?? '' }}</span>
                </div>
                <hr>
              @endif
              {{-- Buyer’s Agent Compensation --}}
              {{-- Is the Seller actively seeking to purchase another property? --}}
              @if (isset($auction->get->looking_other_property) || isset($auction->get->looking_other_property_com) || isset($auction->get->looking_other_property_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1">
                  <h4>Is the Seller actively seeking to purchase another property?</h4>
                    <span class="removeBold">{{ $auction->get->looking_other_property ?? $auction->get->looking_other_property_com ?? $auction->get->looking_other_property_vac ?? '' }}</span>
                </div>
                <hr>
              @endif
              {{-- Is the Seller actively seeking to purchase another property? --}}
              {{-- Title Company Information --}}
              <h4>Title Company Information:</h4>
              @if (isset($auction->get->title_company_name) || isset($auction->get->title_company_name_com) || isset($auction->get->title_company_name_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Name:
                    <span class="removeBold">{{ $auction->get->title_company_name ?? $auction->get->title_company_name_com ?? $auction->get->title_company_name_vac ?? '' }}</span>
                </div>
              @endif
              @if (isset($auction->get->title_company_address) || isset($auction->get->title_company_address_com) || isset($auction->get->title_company_address_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Address:
                    <span class="removeBold">{{ $auction->get->title_company_address ?? $auction->get->title_company_address_com ?? $auction->get->title_company_address_vac ?? ''  }}</span>
                </div>
              @endif
              @if (isset($auction->get->title_company_phone) || isset($auction->get->title_company_phone_com) || isset($auction->get->title_company_phone_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Phone Number:
                    <span class="removeBold">{{ $auction->get->title_company_phone ?? $auction->get->title_company_phone_com ?? $auction->get->title_company_phone_vac ?? ''  }}</span>
                </div>
              @endif
              @if (isset($auction->get->title_company_email) || isset($auction->get->title_company_email_com) || isset($auction->get->title_company_email_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Email:
                    <span class="removeBold">{{ $auction->get->title_company_email ?? $auction->get->title_company_email_com ?? $auction->get->title_company_email_vac ?? ''  }}</span>
                </div>
              @endif
              {{-- Title Company Information --}}
              <hr>
              {{-- Listing information --}}
              @if ($auction->user->user_type == 'agent')
                <h4>Listing Agent Information:</h4>
              @else
                <h4>Seller’s Information:</h4>
              @endif

              @if (isset($auction->get->tagent_first_name) || isset($auction->get->tagent_first_name_com) || isset($auction->get->tagent_first_name_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  First Name:
                    <span class="removeBold">{{ $auction->get->tagent_first_name ?? $auction->get->tagent_first_name_com ?? $auction->get->tagent_first_name_vac ?? ''  }}</span>
                </div>
              @endif
              @if (isset($auction->get->agent_last_name) || isset($auction->get->agent_last_name_com) || isset($auction->get->agent_last_name_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Last Name:
                    <span class="removeBold">{{ $auction->get->agent_last_name ?? $auction->get->agent_last_name_com ?? $auction->get->agent_last_name_vac ?? ''  }}</span>
                </div>
              @endif
              @if (isset($auction->get->agent_phone) || isset($auction->get->agent_phone_com) || isset($auction->get->agent_phone_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Phone Number:
                    <span class="removeBold">{{ $auction->get->agent_phone ?? $auction->get->agent_phone_com ?? $auction->get->agent_phone_vac ?? ''  }}</span>
                </div>
              @endif
              @if (isset($auction->get->agent_email) || isset($auction->get->agent_email_com) || isset($auction->get->agent_email_vac))
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Email:
                    <span class="removeBold">{{ $auction->get->agent_email ?? $auction->get->agent_email_com ?? $auction->get->agent_email_vac ?? ''  }}</span>
                </div>
              @endif
              @if ($auction->user->user_type == 'agent')
                @if (isset($auction->get->agent_brokerage) || isset($auction->get->agent_brokerage_com) || isset($auction->get->agent_brokerage_vac))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Brokerage:
                      <span class="removeBold">{{ $auction->get->agent_brokerage ?? $auction->get->agent_brokerage_com ?? $auction->get->agent_brokerage_vac ?? ''  }}</span>
                  </div>
                @endif
                @if (isset($auction->get->agent_license_no) || isset($auction->get->agent_license_no_com) || isset($auction->get->agent_license_no_vac))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Real Estate License #:
                      <span class="removeBold">{{ $auction->get->agent_license_no ?? $auction->get->agent_license_no_com ?? $auction->get->agent_license_no_vac ?? ''  }}</span>
                  </div>
                @endif
                @if (isset($auction->get->agent_mls_id) || isset($auction->get->agent_mls_id_com) || isset($auction->get->agent_mls_id_vac))
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    NAR Member ID (NRDS ID):
                      <span class="removeBold">{{ $auction->get->agent_mls_id ?? $auction->get->agent_mls_id_com ?? $auction->get->agent_mls_id_vac ?? ''  }}</span>
                  </div>
                @endif
              @endif
              {{-- Listing information --}}
        </div>


        {{-- <h4>Income/commercial property Information</h4>
        <div class="d-flex" style="flex-wrap: wrap;">
          @if (@$auction->get->commercial_total_buildings != null)
            <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
              Total number of buildings: <span class="removeBold"> {{ @$auction->get->commercial_total_buildings }}
              </span></div>
          @endif
          @if (@$auction->get->commercial_total_units != null)
            <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
              Total number of units: <span class="removeBold"> {{ @$auction->get->commercial_total_units }} </span>
            </div>
          @endif
          @if (@$auction->get->commercial_unit_sqft != null)
            <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
              Sqft heated for each unit: <span class="removeBold"> {{ @$auction->get->commercial_unit_sqft }}</span>
          @endif
          @if (@$auction->get->commercial_occupied_or_vacant != null)
            <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Are
              units occupied or vacant? <span class="removeBold">
                {{ @$auction->get->commercial_occupied_or_vacant }}</span>
          @endif
          @if (@$auction->get->commercial_rental_amount != null)
            <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
              Current rental amount: <span class="removeBold"> {{ @$auction->get->commercial_rental_amount }}</span>
          @endif
          @if (@$auction->get->commercial_expected_rental_amount != null)
            <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
              Expected rental amount: <span class="removeBold">
                {{ @$auction->get->commercial_expected_rental_amount }}</span>
          @endif
          @if (@$auction->get->commercial_annual_gross_income != null)
            <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
              Annual gross income: <span class="removeBold">
                {{ @$auction->get->commercial_annual_gross_income }}</span>
          @endif
          @if (@$auction->get->commercial_annual_expenses != null)
            <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
              Annual expenses: <span class="removeBold"> {{ @$auction->get->commercial_annual_expenses }}</span>
          @endif
          @if (@$auction->get->commercial_annual_net_income != null)
            <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
              Annual net income: <span class="removeBold"> {{ @$auction->get->commercial_annual_net_income }}</span>
          @endif
          @if (@$auction->get->commercial_monthly_rent != null)
            <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
              Total monthly rent: <span class="removeBold"> {{ @$auction->get->commercial_monthly_rent }}</span>
          @endif
          @if (@$auction->get->commercial_lease_terms != null)
            <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
              Lease terms: <span class="removeBold"> {{ @$auction->get->commercial_lease_terms }}</span>
          @endif
          @if (@$auction->get->commercial_tenant_pays != null)
            <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
              Tenant pays: <span class="removeBold"> {{ @$auction->get->commercial_tenant_pays }}</span>
          @endif
          @if (@$auction->get->commercial_landlord_pays != null)
            <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
              Landlord pays: <span class="removeBold"> {{ @$auction->get->commercial_landlord_pays }}</span>
          @endif
        </div> --}}
            {{-- @if (@$auction->get->title_company_name != null)
            <hr>
            <h4>Company Info </h4>
            <div class="d-flex" style="flex-wrap: wrap;">
                @if (@$auction->get->title_company_name != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Name:<span class="removeBold"> {{ @$auction->get->title_company_name }}
                    </span>
                  </div>
                @endif
                @if (@$auction->get->title_company_address != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Address:<span class="removeBold"> {{ @$auction->get->title_company_address }}
                    </span>
                  </div>
                @endif
                @if (@$auction->get->title_company_phone != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Phone Number:<span class="removeBold"> {{ @$auction->get->title_company_phone }}
                    </span>
                  </div>
                @endif
                @if (@$auction->get->title_company_email != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Email:<span class="removeBold"> {{ @$auction->get->title_company_email }}
                    </span>
                  </div>
                @endif
            </div>
            @endif
            <hr>
            <h4>Seller’s Agent Info</h4>
            <div class="d-flex" style="flex-wrap: wrap;">
              @if (@$auction->get->agent_first_name != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  First Name: <span class="removeBold"> {{ @$auction->get->agent_first_name }}</span> </span></div>
              @endif
              @if (@$auction->get->agent_last_name != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Last Name: <span class="removeBold"> {{ @$auction->get->agent_last_name }}</span> </span></div>
              @endif
              @if (@$auction->get->agent_phone != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Phone Number: <span class="removeBold"> {{ @$auction->get->agent_phone }}</span> </span></div>
              @endif
              @if (@$auction->get->agent_email != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Email: <span class="removeBold"> {{ @$auction->get->agent_email }}</span> </span></div>
              @endif
              @if (@$auction->get->agent_brokerage != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Brokerage: <span class="removeBold"> {{ @$auction->get->agent_brokerage }}</span> </div>
              @endif
              @if (@$auction->get->agent_license_no != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Real Estate License #: <span class="removeBold"> {{ @$auction->get->agent_license_no }}</span> </div>
              @endif
              @if (@$auction->get->agent_mls_id != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> NAR Member ID (NRDS ID): <span class="removeBold"> {{ @$auction->get->agent_mls_id }}</span> </div>
              @endif
              @if (@$auction->get->realEstateAgent != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Listed By: Real Estate Agent: <span class="removeBold"> {{ @$auction->get->realEstateAgent }}</span> </div>
              @endif
            </div>
            <div class="row">
              @if (isset($auction->get) && isset($auction->get->video) && $auction->get->video != null)
              <div class="col-md-6 col-6 pt-2 fw-bold">Video:
                  <span class="removeBold">
                      <video src="{{ asset($auction->get->video) }}" style="width:100%;height:29vh;"
                          controls autoplay></video>

                      </span>
                  </div>
                  @endif
                  @if (isset($auction->get) && isset($auction->get->photo) && $auction->get->photo != null)
                <div class="col-md-6 col-6 pt-2 fw-bold">Photo:
                  <span class="removeBold">
                    <img src="{{ asset($auction->get->photo) }}" style="width:100%;height:29vh;" />
                  </span>
                </div>
              @endif
            </div>
            <hr>
            <h4>Auction Terms</h4>
            <div class="d-flex" style="flex-wrap: wrap;">
              @if (@$auction->get->price != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Price: <span class="removeBold"> {{ @$auction->get->price }}</span> </div>
              @endif
              @if (@$auction->get->term_financings != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Acceptable Currency/ Financing:
                  @if (gettype(@$auction->get->term_financings) == 'array')
                    @foreach (@$auction->get->term_financings as $item)
                      <span class="badge bg-secondary removeBold">{{ $item }}</span>
                    @endforeach
                  @endif
                </div>
              @endif
              @if (@$auction->get->escrow_amount != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Escrow Amount: <span class="removeBold"> {{ @$auction->get->escrow_amount }} </span></div>
              @endif
              <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                Inspection Period: <span
                  class="removeBold">{{ $bid->inspection_period ?? ($counterView->get->inspection_period ?? '') }}</span>
              </div>
              @if (@$auction->get->closing_days != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Number of days seller will accept for closing: <span class="removeBold">
                    {{ @$auction->get->closing_days }} </span></div>
              @endif
              @if (@$auction->get->buyer_agent_commission != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Buyer's Agent Commission: <span class="removeBold"> {{ @$auction->get->buyer_agent_commission }}
                  </span></div>
              @endif
              @if (@$auction->get->buyer_premium != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Buyer's Premium: <span class="removeBold"> {{ @$auction->get->buyer_premium }} </span></div>
              @endif
              <div class="d-none">
                @if (@$auction->get->seller_premium != null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Seller's Premium: <span class="removeBold"> {{ @$auction->get->seller_premium }} </span></div>
                @endif
                @if (@$auction->get->wholesaler_fee != null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Wholesaler fee: <span class="removeBold"> {{ @$auction->get->wholesaler_fee }} </span></div>
                @endif
                @if (@$auction->get->builder_fee != null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Builder fees to Buyer: <span class="removeBold"> {{ @$auction->get->builder_fee }} </span></div>
                @endif
                @if (@$auction->get->builder_incentives != null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Builder Incentives: <span class="removeBold"> {{ @$auction->get->builder_incentives }} </span></div>
                @endif
                @if (@$auction->get->attorney_fee != null)
                  <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                    Attorney Fee to Buyer for contract reviewal: <span class="removeBold">
                      {{ @$auction->get->attorney_fee }} </span></div>
                @endif
              </div>
            </div>
            <div class="d-flex" style="flex-wrap: wrap;">
              @if (@$auction->get->prop_condition != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Property condition: <span class="removeBold">{{ @$auction->get->prop_condition }}</span>
                </div>
              @endif
              @if (@$auction->get->known_repairs != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Known Repairs that need to be done: <span class="removeBold">
                    {{ @$auction->get->known_repairs }}</span> </div>
              @endif
              @if (@$auction->get->arv != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  ARV: <span class="removeBold"> {{ @$auction->get->arv }}</span> </div>
              @endif
              @if (@$auction->get->contingencies != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Contingencies Seller will accept: <span class="removeBold"> {{ @$auction->get->contingencies }}</span>
                </div>
              @endif
              @if (@$auction->get->title_company != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Title Company: <span class="removeBold"> {{ @$auction->get->title_company }}</span> </div>
              @endif
              @if (@$auction->get->title_agent_name != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Title Agent Name: <span class="removeBold"> {{ @$auction->get->title_agent_name }}</span> </div>
              @endif
              @if (@$auction->get->title_company_phone != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Title Company Phone #: <span class="removeBold"> {{ @$auction->get->title_company_phone }}</span>
                </div>
              @endif
              @if (@$auction->get->title_company_email != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Title Company Email: <span class="removeBold"> {{ @$auction->get->title_company_email }}</span> </div>
              @endif
              @if (@$auction->get->looking_other_property != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i> Is
                  Seller looking to purchase another property? <span class="removeBold">
                    {{ @$auction->get->looking_other_property }}</span>
                </div>
              @endif
              @if (@$auction->get->listing_link != null)
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Link to listing for Seller’s ad on the Buyer’s auction: <a href="{{ @$auction->get->listing_link }}"
                    target="_blank" rel="noopener noreferrer"><span class="removeBold">
                      {{ @$auction->get->listing_link }}</span></a> </div>
              @endif
              @if (@$auction->get->video_url != '')
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Property Video: <a href="{{ @$auction->get->video_url }}" target="_blank"
                    rel="noopener noreferrer"><span class="removeBold"> {{ @$auction->get->video_url }}</span></a>
                </div>
              @endif
              @if (@$auction->get->explaining_video != '')
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Video from agent or seller explaining the property: <a href="{{ @$auction->get->explaining_video }}"
                    target="_blank" rel="noopener noreferrer"><span class="removeBold">
                      {{ @$auction->get->explaining_video }}</span></a> </div>
              @endif
              @if (@$auction->get->note != '')
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1"><i class="fa-regular fa-check-square"></i>
                  Floor plan: <a href="{{ url(@$auction->get->note) }}" target="_blank"
                    class="btn btn-secondary btn-sm">Download Floor Plan</a> </div>
              @endif
              @if (@$auction->get->audio != '')
                <div class="col-md-12 col-12 fw-bold mt-1 mb-1">
                  <audio controls>
                    <source src="{{ url(@$auction->get->audio) }}" type="audio/ogg">
                    Your browser does not support the audio tag.
                  </audio>
                </div>
              @endif
            </div> --}}

          </div>
        </div>
        <!-- End  -->
        <!-- Location Box  -->
        <div class="card location d-none">
          <div class="card-header">
            <h5>Location</h5>
          </div>
          <div class="card-body">
            <div class="locationMap position-relative">
              <img class="w-100" src="https://bidyouroffer.com/wp-content/uploads/2022/09/map-placeholder.jpg"
                alt="">
              <div class="right position-absolute">
                <button class="btn btn-sm"><i class="fa-solid fa-map-marker"></i> View Map</button>
                <button class="btn btn-sm">Get Direction</button>

              </div>
            </div>
            <br>
            <p><b>534 Pinellas Bayway S</b></p>
          </div>
        </div>
        <!-- End  -->
        <!-- Review  -->
        <div class="card review">
          <div class="card-body d-flex align-items-center">
            <div class="left d-flex align-items-center">
              <img class="w-25" src="https://ppt1080.b-cdn.net/images/avatar/none.png" alt="">
              <div>
                <p class="mb-0"><a href="{{ route('author', [@$auction->user_id]) }}"><b>Seller's
                      Agent
                      Details</b></a><span></span>
                  <span class="start opacity-50">
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                  </span>
                </p>
                <p class="mb-0">...</p>
                <p class="mb-0 opacity-50">AbbyS1 • last online 5 days ago.</p>
              </div>
            </div>
            <div class="right text-center">
              <a href="author.html"><button class="btn">Message</button></a>
              <a href="author.html"><button class="btn">View Profile</button></a>

            </div>
          </div>
        </div>
        <!-- End  -->
      </div>
      <div class="col-sm-12 col-md-4 col-lg-4 rightCol">
        <h1>{{ @$auction->get->address }}</h1>
        <hr>
        @inject('carbon', 'Carbon\Carbon')
        @php
          if ($auction->auction_length > 0) {
              $start = $carbon::now();
              $end = $carbon::parse($auction->created_at)->addDays($auction->auction_length);
              $diff = $start->diffInDays($end, false);
          }
        @endphp
        @if (isset($auction->auction_type) && $auction->auction_type == 'Auction Listing' && $auction->auction_length > 0)
          @php
              $diff_d = $diff < 0 ? 0 : $diff;
              $diff_H = $diff < 0 ? 0 : $start->diff($end)->format('%H');
              $diff_I = $diff < 0 ? 0 : $start->diff($end)->format('%I');
              $diff_S = $diff < 0 ? 0 : $start->diff($end)->format('%S');
          @endphp
          <div id="countdown" class="time d-flex justify-content-between text-center flex-wrap pb-2">
              @if ($auction->auction_ended)
                  <div class="d-flex justify-content-center align-items-center w-100">
                      <h4 class="text-success">Auction Ended</h4>
                  </div>
              @else
                  <div>
                      <h5><b class="timer-d"> {{ $diff_d }} </b></h5>
                      <h6 class="opacity-50">Days</h6>
                  </div>
                  <div>
                      <h5><b class="timer-h"> {{ $diff_H }} </b></h5>
                      <h6 class="opacity-50">Hrs</h6>
                  </div>
                  <div>
                      <h5><b class="timer-m"> {{ $diff_I }} </b></h5>
                      <h6 class="opacity-50">Mins</h6>
                  </div>
                  <div>
                      <h5><b class="timer-s"> {{ $diff_S }} </b></h5>
                      <h6 class="opacity-50">Secs</h6>
                  </div>
              @endif
          </div>
    @endif
        @php
          $highest_bid_price = @$auction->get->starting_price;
          // $highest_bidder = @$auction->bids->where('price', '>' , $highest_bid_price)->orderBy('price', 'desc')->first();
          $highest_bidder = $auction->bids
            ->filter(function ($bid) use ($highest_bid_price) {
                return $bid->price > $highest_bid_price;
            })
            ->sortByDesc('price')
            ->first();
          $my_bid = @$auction->bids->where('user_id', $auth_id)->first();
        @endphp
        @if (@$auction->user_id != $auth_id)
          <a href="{{ route('auction-chat', ['seller-property', $auction->id]) }}" class="btn btn-success w-100"> <i
              class="fa-solid fa-paper-plane"></i> Send Message</a>
          <button id="float-button" data-id="{{ $auction->id }}" class="">AI Chat</button>


          {{-- Integrating Pop Up chat --}}

          <div id="chatContainer" class="chat-container" style="width: 629px;">
            <div class="chat-header">
              <h3 style="font-size: 20px;">Chat Support</h3>
              <button id="chatCloseBtn" style="width: 41px;margin-left: 90px!;" class="chat-close-btn">&times;</button>
            </div>

            <div class="chat-input">
              <input type="text" id="messageInput" placeholder="Type your message">
              <button id="sendMessageBtn" class="send-message-btn">Send</button>
            </div>
          </div>
        @endif

        @if ($auth_id)
          @if (in_array(auth()->user()->user_type, ['admin', 'agent']))
            <button class="btn w-100"
              onclick="javascript:window.location='{{ route('seller_property_add_bid', @$auction->id) }}';"
              {{ @$auction->user_id == $auth_id || (@$auction->sold == 1 && $auction->sold_date != null) ? 'disabled' : '' }}>
              <span class="bid">Bid Now </span>
              <span
                class="badge bg-light float-end text-dark">${{ number_format($highest_bid_price, 2, '.', ',') }}</span>
              @if (@$auction->sold)
                <span class="badge bg-danger">Sold</span>
              @endif
            </button>
          @endif
        @else
          <a href="{{ route('login') }}">
            <button class="btn w-100">
              <span class="bid">Login for Bid </span>
              <span
                class="badge bg-light float-end text-dark">${{ number_format($highest_bid_price, 2, '.', ',') }}</span>
            </button>
          </a>
        @endif
        <!-- Highest Bider   -->
        <div class="card higestBider">
          @if($auction->user_id == auth()->user()->id && $bids->count() > 0)
            <div class="d-flex align-items-baseline justify-content-center">
              @if ($auction->display_bids == 0)
                <form action="{{ route('property.bids.visibility', ['id' => $auction->id, 'vis' => 'show']) }}"  method="post">
                  @csrf
                  <button class="btn bg-success btn-sm px-3 mb-3 mt-0">Show Bids</button>
                </form>
              @else
                <form action="{{ route('property.bids.visibility', ['id' => $auction->id, 'vis' => 'hide']) }}" method="post">
                  @csrf
                  <button class="btn bg-danger btn-sm px-3 mb-3 mt-0">Hide Bids</button>
                </form>
              @endif
            </div>
          @endif
          <div class="card-body">
            @if ($highest_bidder)
              <p><b>{{ $highest_bidder->user->name ?? '' }}</b> is the highest bidder.</p>
            @else
              <p>No one has bid on this auction.</p>
            @endif

            <div class="accordion" id="accordionExample">
              @php
                $counterBid = App\Models\PropertyAuctionBid::all();
              @endphp
              {{-- @dd($bids); --}}
              @if ($auction->display_bids == 1)
                @foreach ($bids as $key => $bid)
                  {{-- @if ($loop->last)
                    @dd($bid->get->financing)
                  @endif --}}
                  @if ($bid->counter_id == null)
                    <div class="accordion-item border-0">
                      <div class="accordion" type="button" data-bs-toggle="collapse"
                        data-bs-target="#item{{ $key + 1 }}" aria-expanded="false"
                        aria-controls="item{{ $key + 1 }}">
                        <div class="d-flex small accordion mr-0 text-center">
                          <div class="col-1">
                            <span class="badge">{{ $loop->iteration }}</span>
                          </div>
                          <div class="col-4">
                            {{ $bid->user->name ?? '' }}
                          </div>
                          @if ($bid->accepted == 'rejected')
                            <div class="col-4 text-right position-relative">
                              ${{ number_format($bid->price, 2, '.', ',') }}
                              <span
                                style="position: absolute; width:65px; margin-left: 54px; right: 0; top: 50%; transform: translateY(-50%); border-top: 2px solid red; z-index: 1;"></span>
                            </div>
                            <div class="col-2 position-relative">
                              <span
                                style="position: absolute; left: 0; right: 0; top: 50%; transform: translateY(-50%); border-top: 2px solid red; z-index: 1;"></span>
                              <span style="position: relative; z-index: 2;">Terms↓</span>
                            </div>
                          @else
                            <div class="col-4 text-right">
                              ${{ number_format($bid->price, 2, '.', ',') }}
                            </div>
                            <div class="col-2">
                              Terms↓
                            </div>
                          @endif

                        </div>
                      </div>
                      <div id="item{{ $key + 1 }}" class="accordion-collapse collapse"
                        aria-labelledby="heading{{ $key + 1 }}" data-bs-parent="#accordionExample">
                        <div class="accordion-body">
                          <div id="bidding_history_data">
                            <div>
                              @if (isset($bid->get->first_name))
                                <p class="d-flex justify-content-between small fw-bold">First Name:
                                  <span class="removeBold">{{ $bid->get->first_name }}</span>
                                </p>
                              @endif
                              @if ($bid->price)
                                <p class="d-flex justify-content-between small fw-bold">Offered Price:
                                  <span class="removeBold">{{ $bid->price }}</span>
                                </p>
                              @endif
                              @if ($bid->escrow_amount)
                                <p class="d-flex justify-content-between small fw-bold">Offered Escrow Deposit:
                                  <span class="removeBold">{{ $bid->escrow_amount }}</span>
                                </p>
                              @endif
                              @if ($bid->get->contingencies != null)
                              <p class="d-flex justify-content-between small fw-bold">Offered Contingencies:
                                <span
                                  class="removeBold">{{ $bid->get->custom_contingencies }}</span>
                              </p>
                              @endif
                              @php
                                $counterView = App\Models\PropertyAuctionBid::with('meta')->latest('id')->first();
                              @endphp
                              @if(isset($bid->get->term_financings))
                              <p class="d-flex justify-content-between small fw-bold">Currency/Financing:
                                <span
                                  class="removeBold">{{ $bid->get->term_financings }}</span>
                              </p>
                              @endif
                              @if (isset($bid->get->closing_days))
                                <p class="d-flex justify-content-between small fw-bold">Offered Closing Date:
                                  <span class="removeBold">{{ $bid->get->closing_days }}</span>
                                </p>
                              @endif
                              @if (isset($bid->get->desired_days))
                                <p class="d-flex justify-content-between small fw-bold">Offered Closing Days:
                                  <span class="removeBold">{{ $bid->get->desired_days }}</span>
                                </p>
                              @endif
                              @if(isset($bid->inspection_period))
                              <p class="d-flex justify-content-between small fw-bold">Inspection Period:
                                <span
                                  class="removeBold">{{ $bid->inspection_period ?? ($counterView->get->inspection_period ?? '') }}</span>
                              </p>
                              @endif
                              @if ($bid->seller_premium)
                                <p class="d-flex justify-content-between small fw-bold">Seller Premium:
                                  <span class="removeBold">{{ $bid->seller_premium }}</span>
                                </p>
                              @endif

                              @if ($bid->buyer_premium)
                                <p class="d-flex justify-content-between small fw-bold">Buyer Premium:
                                  <span class="removeBold">{{ $bid->buyer_premium }}</span>
                                </p>
                              @endif

                              @if ($auction->user_id == $auth_id)
                                @if (optional($bid->get)->video_url)
                                  <p>
                                    <a href="{{ $bid->video_url ?? ($counterView->get->video_url ?? '') }}"
                                      class="btn btn-sm btn-primary" target="_blank">View
                                      Video</a>
                                  </p>
                                @endif

                                @if (optional($bid->get)->note)
                                  <p>
                                    <a href="{{ url($bid->get->note) }}" class="btn btn-sm btn-primary"
                                      target="_blank">Proof
                                      of funds/pre-approval letter</a>
                                  </p>
                                @endif
                                @if (optional($bid->get)->card)
                                  <p>
                                    <a href="{{ asset($bid->get->card) }}" target="_blank">
                                      <img src="{{ asset($bid->get->card) }}" alt="" style="width: 150px;">
                                    </a>
                                  </p>
                                @endif

                                @if (optional($bid->get)->audio)
                                  <p>
                                    <audio class="audio-fluid" controls style="width: 100%;">
                                      <source src="{{ asset($bid->get->audio) }}" type="audio/mp3">
                                      Your browser does not support the audio tag.
                                    </audio>
                                  </p>
                                @endif
                              @endif
                              @if ($bid->accepted == 'rejected')
                                <div class="bg-danger p-2 borderless row justify-content-center text-white rounded">
                                  Rejected</div>
                              @elseif ($bid->accepted != 'rejected')
                                <div class="form-group d-flex justify-content-space gap-1">
                                  <div style="flex-grow: 1;">
                                    @if ($auction->user_id == $auth_id && !$auction->sold)
                                      <form action="{{ route('acceptPABid') }}" method="post">
                                        @csrf
                                        <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                                        <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                                        <input type="hidden" name="counterPrice" value="{{ $bid->price }}">
                                        <button type="submit" class="btn btn-success">Accept</button>
                                      </form>
                                    @endif
                                  </div>
                                  <div>
                                    @if ($auction->user_id == $auth_id && !$auction->sold)
                                      <form id="deleteForm" method="post" action="{{ route('rejectPABid') }}">
                                        @csrf
                                        <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                                        <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                                        <button type="button" style="background-color:#da2a43" class="btn btn-danger"
                                          onclick="showToast()">Reject</button>
                                      </form>
                                    @endif
                                  </div>
                                </div>

                                {{-- @dd($auction); --}}
                                {{-- @if (auth()->user()->id == $bid->user->id || (auth()->user()->user_type == 'agent' || auth()->user()->user_type == 'admin'))
                                @auth --}}
                                @auth
                                  @if (auth()->user()->id == $bid->user->id ||
                                          (auth()->user()->user_type == 'agent' || auth()->user()->user_type == 'admin'))
                                    <div class="form-group biddingOperations">
                                      @if (!$auction->sold)
                                        <form action="{{ route('add-counterBiding', ['bid_id' => $bid->id, 'auction_id' => $auction->id]) }}" method="get">
                                          @csrf
                                          <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                                          <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                                          {{-- <input type="number" name="counterAmount" class="form-control" required> --}}
                                          <div class="d-flex gap-1">
                                            <button type="submit" class="btn btn-primary">
                                              Counter Bid
                                            </button>
                                            {{-- <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                              data-bs-target="#exampleModal-{{ isset($bid->id) ? $bid->id : '' }}">
                                              Counter Terms
                                            </button> --}}
                                          </div>
                                        </form>
                                      @endif
                                      @php
                                        $allBids = App\Models\PropertyAuctionBid::where('counter_id', $bid->id)->with('meta')
                                            ->orderByDesc('created_at')
                                            ->get();
                                      @endphp
                                      <div class="form-group">
                                        @foreach ($allBids as $key => $countBid)
                                          <form action="{{ route('acceptPABid') }}" method="post">
                                            @csrf
                                            <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                                            <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                                          </form>
                                        @endforeach
                                      </div>
                                      <div class="form-group">
                                        @if (!$auction->sold)
                                          @foreach ($allBids as $key => $countBid)
                                            @if(isset($countBid->get->first_name))
                                              <p class="d-flex justify-content-between small fw-bold">First Name:
                                                <span
                                                  class="removeBold">{{ $countBid->get->first_name }}</span>
                                              </p>
                                            @endif
                                            @if ($countBid->price)
                                              <p class="d-flex justify-content-between small fw-bold">Offered Price:
                                                <span class="removeBold">{{ $countBid->price }}</span>
                                              </p>
                                            @endif
                                            @if ($countBid->escrow_amount)
                                              <p class="d-flex justify-content-between small fw-bold">Offered Escrow Deposit:
                                                <span class="removeBold">{{ $countBid->escrow_amount }}</span>
                                              </p>
                                            @endif
                                            @if ($countBid->get->contingencies != null)
                                            <p class="d-flex justify-content-between small fw-bold">Offered Contingencies:
                                              <span
                                                class="removeBold">{{ $countBid->get->custom_contingencies }}</span>
                                            </p>
                                            @endif
                                            @php
                                              $counterView = App\Models\PropertyAuctionBid::with('meta')->latest('id')->first();
                                            @endphp
                                            @if(isset($countBid->get->term_financings))
                                            <p class="d-flex justify-content-between small fw-bold">Currency/Financing:
                                              <span
                                                class="removeBold">{{ $countBid->get->term_financings }}</span>
                                            </p>
                                            @endif
                                            @if (isset($countBid->get->closing_days))
                                              <p class="d-flex justify-content-between small fw-bold">Offered Closing Date:
                                                <span class="removeBold">{{ $countBid->get->closing_days }}</span>
                                              </p>
                                            @endif
                                            @if (isset($countBid->get->desired_days))
                                              <p class="d-flex justify-content-between small fw-bold">Offered Closing Days:
                                                <span class="removeBold">{{ $countBid->get->desired_days }}</span>
                                              </p>
                                            @endif
                                            @if (isset($countBid->get->agent_commission))
                                              <p class="d-flex justify-content-between small fw-bold">Acceptable Real Estate Agent Commission:
                                                <span class="removeBold">{{ $countBid->get->agent_commission }}</span>
                                              </p>
                                            @endif
                                            @if (isset($countBid->get->offer_expiry))
                                              <p class="d-flex justify-content-between small fw-bold">Offer Expires:
                                                <span class="removeBold">{{ $countBid->get->offer_expiry }}</span>
                                              </p>
                                            @endif
                                            @if (isset($countBid->get->additional_details_counter_terms))
                                              <p class="d-flex justify-content-between small fw-bold">Additional Details or Countered Terms:
                                                <span class="removeBold">{{ $countBid->get->additional_details_counter_terms }}</span>
                                              </p>
                                            @endif
                                            <div class="d-flex justify-content-between align-items-center">
                                              <form action="{{ route('acceptPABid') }}" method="post">
                                                @csrf
                                                <input type="hidden" name="auction_id"
                                                  value="{{ $auction->id }}">
                                                <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                                                <input type="hidden" name="counterPrice"
                                                  value="{{ $countBid->price }}">
                                                @if (auth()->user()->user_type == 'agent' || auth()->user()->user_type == 'admin')
                                                  <button type="submit"
                                                    class="badge bg-success p-2 borderless">Accept</button>
                                                @endif
                                              </form>
                                              <form action="{{ route('destroyCounter', $countBid->id) }}"
                                                method="post">
                                                @csrf
                                                @if (auth()->user()->user_type == 'agent' || auth()->user()->user_type == 'admin')
                                                  <button type="submit"
                                                    class="badge bg-danger p-2 borderless">Reject</button>
                                                @endif
                                              </form>
                                            </div>
                                          @endforeach
                                        @endif
                                      </div>
                                    </div>
                                  @endauth
                                @endif
                              @endif
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  @endif
                @endforeach
              @endif
            </div>
          </div>

        </div>
        <button class="btn w-100 mt-0">
          <span class="bid m-0"><i class="fa-solid fa-user"></i> </span>
        </button>
        <!-- End  -->
        <!-- Details Of Items -->
        <!-- End -->
        <!-- Social Details  -->
        <div class="p-4 card">
          <p class="text-600">Share this link via</p>
          <div class="qr-code" style="width: 100%; height:200px;">
            {{ qr_code(route('view-pl', @$auction->id), 200) }}
          </div>
          <div class="card-social">
            <ul class="icons">
              <a href="">
                <i class="fa-brands fa-facebook-f"></i>
              </a>

              <a href="">
                <i class="fa-brands fa-twitter"></i>
              </a>
              <a href="">
                <i class="fa-brands fa-instagram"></i>
              </a>
              <a href="">
                <i class="fa-brands fa-pinterest"></i>
              </a>
              <a href="">
                <i class="fa-brands fa-linkedin"></i>
              </a>
            </ul>
            <p class="small opacity-8">Or copy link</p>
            <div class="field">
              <i class="fa-solid fa-link"></i>
              <input type="text" readonly="" id="copylink"
                value="https://bidyouroffer.com/listing/534-pinellas-bayway-s-204-tierra-verde-fl-33715-4/">
              <button class="btn-primary btn-sm text-600 js-copy-link text-center border-0"
                style="min-width:60px;">Copy</button>
            </div>

          </div>
        </div>
        <!-- End  -->
      </div>
    </div>
  </div>
  <hr>
  <!-- Recommmended Section  -->
  <div class="container buyerOfferContentDetails">
    <h3 class="text-600 mb-4">Recommended For You</h3>
    <div class="cardsDetails row  justify-content-start">
      <!-- Card 1 -->
      <div class="col-sm-6 col-md-4 col-lg-3 mb-3">
        <div class="card ">
          <img src="https://bidyouroffer.com/wp-content/uploads/2022/10/165522238955562a8b07535346697508007-300x200.jpg"
            class="card-img-top" alt="...">
          <div class="card-body pb-2 pt-2">
            <h5 class="card-title"><a href="">1199 Randall Way, Brownsburg, IN 46112 </a></h5>
            <div class="houseDetails mb-1">
              <span>
                <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                    src="{{ asset('assets/fontawesome/svgs/thin/bed-front.svg') }}" alt="bed icon" width="15"><b>
                    4</b></span>
                <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                    src="{{ asset('assets/fontawesome/svgs/thin/bath.svg') }}" alt="bed icon" width="15"><b>
                    2</b></span>
                <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                    src="{{ asset('assets/fontawesome/svgs/thin/ruler-triangle.svg') }}" alt="bed icon"
                    width="15"><b> 1,643 </b>Sq Ft</span>
              </span>
              - House for sale
            </div>
            <p class="card-text mb-1"><span class="badge bg-secondary">land/lots</span> <span
                class="float-end"><span><b>MLS ID</b></span> <span>#12345</span></p>
            <p class="m-0"><svg xmlns="http://www.w3.org/2000/svg" class="clock" fill="none"
                viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg><b>28d 03:15:29</b></p>
          </div>
          <div class="card-footer bg-light">
            <div class="row">
              <div class="col-6 left">
                <!-- Barcode  -->
                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus"
                  data-bs-placement="top" data-bs-content="Scan Qr Code" xmlns="http://www.w3.org/2000/svg"
                  fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                  </path>
                </svg>
                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus"
                  data-bs-placement="top" data-bs-content="Send Message" xmlns="http://www.w3.org/2000/svg"
                  fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                  </path>
                </svg>
                <!-- FAvourite  -->
                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus"
                  data-bs-placement="top" data-bs-content="Add Favorites" xmlns="http://www.w3.org/2000/svg"
                  fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                  </path>
                </svg>
              </div>
              <div class="col-6 right text-end">
                <b>$1,000</b>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- Card 2 -->
      <div class="col-sm-6 col-md-4 col-lg-3 mb-3">
        <div class="card ">
          <img src="https://bidyouroffer.com/wp-content/uploads/2022/10/165522238955562a8b07535346697508007-300x200.jpg"
            class="card-img-top" alt="...">
          <div class="card-body">
            <h5 class="card-title"><a href="">1199 Randall Way, Brownsburg, IN 46112 </a></h5>
            <div class="houseDetails">
              <span>
                <span><b>4</b> bds</span>
                <span><b>2</b> ba</span>
                <span><b>1,643</b> sqft</span>
              </span>
              - House for sale
            </div>
            <p class="card-text"><span class="badge bg-secondary">land/lots</span> <span class="float-end"><span><b>MLS
                    ID</b></span> <span>#12345</span></p>
          </div>
          <div class="card-footer bg-light">
            <div class="row">
              <div class="col-6 left">
                <!-- Barcode  -->
                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus"
                  data-bs-placement="top" data-bs-content="Scan Qr Code" xmlns="http://www.w3.org/2000/svg"
                  fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                  </path>
                </svg>
                <!-- Message  -->
                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus"
                  data-bs-placement="top" data-bs-content="Send Message" xmlns="http://www.w3.org/2000/svg"
                  fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                  </path>
                </svg>
                <!-- FAvourite  -->
                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus"
                  data-bs-placement="top" data-bs-content="Add Favorites" xmlns="http://www.w3.org/2000/svg"
                  fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                  </path>
                </svg>
              </div>
              <div class="col-6 right text-end">
                <b>$1,000</b>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>

  @foreach ($bids as $key => $bid)
    <!-- Modal -->
    <div class="row">
      <div class="col-12">
        <div class="modal fade" id="exampleModal-{{ isset($bid->id) ? $bid->id : '' }}" tabindex="-1"
          aria-labelledby="exampleModalLabel" aria-hidden="true" style="--bs-modal-width:70% !important;"
          aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Counter Terms</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <table class="table">
                  {{-- @php $counterTerms=$bids->where('id', isset($bid->id))->get(); @endphp --}}
                  {{-- @if (isset($counterTerms)) --}}
                  {{-- @if ($counterTerms->id != null)
                      <tr>
                        <th>ID#</th>
                        <td>{{ $counterTerms->id }}</td>
                      </tr>
                    @endif --}}
                  @if (isset($bid->price))
                    <tr>
                      <th>Price:</th>
                      <td>{{ $bid->price }}</td>
                    </tr>
                  @endif
                  @if (isset($bid->get->financing))
                    <tr>
                      <th>Currency/Financing:</th>
                      <td>{{ $bid->get->financing }}</td>
                    </tr>
                  @endif
                  @if (isset($bid->get->escrow_amount))
                    <tr>
                      <th>Offered Escrow Amount(Reserve Terms):</th>
                      <td>{{ $bid->get->escrow_amount }}</td>
                    </tr>
                  @endif
                  @if (isset($bid->get->escrow_amount2))
                    <tr>
                      <th>Offered Escrow Amount(Buy Now Terms):</th>
                      <td>{{ $bid->get->escrow_amount2 }}</td>
                    </tr>
                  @endif
                  @if (isset($bid->get->inspection_period))
                    <tr>
                      <th>Inspection Period(Reserve Terms):</th>
                      <td>{{ $bid->get->inspection_period }}</td>
                    </tr>
                  @endif
                  @if (isset($bid->get->inspection_period2))
                    <tr>
                      <th>Inspection Period(Buy Now Terms):</th>
                      <td>{{ $bid->get->inspection_period2 }}</td>
                    </tr>
                  @endif
                  @if (isset($bid->get->closing_days))
                    <tr>
                      <th>Offered Closing Days(Reserve Terms):</th>
                      <td>{{ $bid->get->closing_days }}</td>
                    </tr>
                  @endif
                  @if (isset($bid->get->closing_days2))
                    <tr>
                      <th>Offered Closing Days(Buy Now Terms):</th>
                      <td>{{ $bid->get->closing_days2 }}</td>
                    </tr>
                  @endif
                  @if (isset($bid->get->desired_days))
                    <tr>
                      <th>Days to Complete the Closing Process:</th>
                      <td>{{ $bid->get->desired_days }}</td>
                    </tr>
                  @endif
                  @if (isset($bid->get->contingencies))
                    <tr>
                      <th>Contingencies:</th>
                      <td>{{ $bid->get->contingencies }}</td>
                    </tr>
                  @endif
                  @if (isset($bid->get->inspection))
                    <tr>
                      <th>Inspection :</th>
                      <td>{{ $bid->get->inspection }}</td>
                    </tr>
                  @endif
                  @if (isset($bid->get->creditForm))
                    <tr>
                      <th>Is the buyer requesting a credit form the seller at closing?</th>
                      <td>{{ $bid->get->creditForm }}</td>
                    </tr>
                  @endif
                  @if (isset($bid->get->offerCredit))
                    <tr>
                      <th>Is the buyer offering a credit to the seller at closing?</th>
                      <td>{{ $bid->get->offerCredit }}</td>
                    </tr>
                  @endif
                  @if (isset($bid->get->buyer_id))
                    <tr>
                      <th>Buyer ID:</th>
                      <td>{{ $bid->get->buyer_id }}</td>
                    </tr>
                  @endif
                  @if (isset($bid->get->video_url))
                    <tr>
                      <th>Personal Video to the Seller:</th>
                      <td>{{ $bid->get->video_url }}</td>
                    </tr>
                  @endif
                  @if (isset($bid->get->proof_of_fund_url))
                    <tr>
                      <th>Proof of funds/Pre-approval letter:</th>
                      <td>{{ $bid->get->proof_of_fund_url }}</td>
                    </tr>
                  @endif
                  {{-- @endif --}}
                </table>
              </div>
              <div class="modal-footer">
                {{-- <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button> --}}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    {{-- Modal --}}
  @endforeach
  @php
    $bidId = isset($bid) ? $bid->id : null;
  @endphp
@endsection

@push('scripts')
  <!-- jQuery -->
  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/timer.jquery/0.9.0/timer.jquery.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

  <!-- Toastr JavaScript -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>


  <script>
    // Toastr Options
    toastr.options = {
      "closeButton": true,
      "positionClass": "toast-top-center",
      "preventDuplicates": true,
      "onclick": null,
      "showDuration": "300",
      "hideDuration": "1000",
      "timeOut": 0,
      "extendedTimeOut": 0,
      "showEasing": "swing",
      "hideEasing": "linear",
      "showMethod": "fadeIn",
      "hideMethod": "fadeOut",
      "toastClass": "custom-toast"
    };

    // Function to display the Toastr toast notification and confirm before form submission
    function showToast() {
      // Define custom HTML content for the toast message with "Yes" and "No" buttons
      var toastContent =
        '<div><span>Are you sure you want to reject this bid?</span><br><br>' +
        '<div class="d-flex justify-content-between"><button type="button" class="btn btn-danger rounded" onclick="rejectBid()">Confirm</button>' +
        '<button type="button" class="btn btn-secondary border-radius-3" onclick="toastr.clear()">Cancel</button></div></div>';

      // Display custom Toastr notification with HTML content
      toastr.clear(); // Clear any existing toastr notifications
      toastr.info(toastContent, '', {
        closeButton: true,
        timeOut: 0,
        extendedTimeOut: 0
      });
    }

    // Function to handle "Yes" button click
    function rejectBid() {
      // Submit the form or perform any other action
      $('#deleteForm').submit();
    }
  </script>


  <script>
    let bid_id = @json($bidId);
    $("#chatForm").submit(function(e) {

      e.preventDefault(); // avoid to execute the actual submit of the form.

      var form = $(this);
      var actionUrl = form.attr('action');
      var userId = $('#userId').val();
      $.ajax({
        type: "POST",
        url: "{{ route('counterBiding', ['bid_id' => ':bid_id']) }}".replace(':bid_id', bid_id),
        data: {
          _token: "{{ csrf_token() }}",
          id: userId,
          formData: form.serialize() // Serialize the form's elements.
        },
        success: function(response) {
          //   alert(response.success); // show response from the php script.
        }
      });

    });





    var floatButton = document.getElementById("float-button");
    var inbox = document.getElementById("chatContainer");
    var closeButton = document.getElementById("close-button");

    floatButton.addEventListener("click", function() {
      var auction_id = $("#float-button").data("id");
      $.ajaxSetup({
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
      });

      $.ajax({
        url: '/chat_gpt',
        method: 'POST',
        data: {
          auction_id: auction_id,
        },
        success: function(res) {
          // Handle the successful response
          $('#chatContainer').fadeToggle();
          if ($('#chatContainer').find('ul.chat-messages').length > 0) {
            // The chatContainer contains the ul.chat-messages element
            console.log('chatContainer contains ul.chat-messages');
          } else {
            $('#chatContainer').empty();
            $('#chatContainer').append(res.html);
          }

          id = res.id;
        },
        error: function(xhr, status, error) {
          // Handle the error
          console.log(error);
        }
      });

    });
    closeButton.addEventListener("click", function() {
      inbox.classList.remove("visible");
    });
  </script>
  {{-- Message Blade Code --}}

  <script>
    $(document).ready(function() {
      function newMessage() {
        question = $(".message-input").val();
        if ($.trim(question) == '') {
          return false;
        }
        // var sent =
        //     `<li class="sent"><!--<img src="http://emilcarlsson.se/assets/mikeross.png" alt="" / style="background:red;"> --><p>${message}</p></li>`;
        // var reply = `<li class="replies"><p>${message}</p></li>`;

        var sent = `
                <div class="d-flex" style="justify-content:flex-end;">

    <li class="send12" style="border-radius: 10px; background: #184f49; display: inline-block; white-space: normal; padding: 5px; padding-top: 7px; padding-right: 16px; padding-bottom: 11px; max-width: 450px;">
        <span class="ms-2 question_listing" style="font-size: 11px; margin-top: 10px;color:white;">
            ${question}
        </span>
    </li>
</div>
`;

        $('.message-input').val(null);
        $('.chat-messages').append(sent);
        scroll_bottom();
        chat_reply(question);
      };

      function chat_reply(question) {
        $.ajaxSetup({
          headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
          }
        });

        // Show loader
        var loader = '<div class="loader"></div>';
        $('.chat-messages').append(loader);
        $('.message-input').prop('disabled', true);
        $('#submit12').prop('disabled', true);

        $.ajax({
          type: "POST",
          url: '/chat_gpt_reply',
          data: {
            question: question,
            id: id,
          },
          dataType: "json",
          success: function(response) {
            setTimeout(() => {
              var reply = `
                    <div class="align-items-end" style="display: flex; justify-content: flex-start;">
                        <li class="replies" style="background:#3b5461; display: inline-block; border-radius: 10px; padding: 5px; padding-top: 7px; padding-right: 16px; padding-bottom: 11px; max-width: 450px;">
                            <span class="ms-2" style="font-size: 11px; margin-top: 10px;color:white;">
                                ${response.bestAnswer}
                            </span>
                        </li>
                    </div>`;

              // Remove the loader
              $('.chat-messages .loader').remove();
              // Append the reply
              $('.chat-messages').append(reply);
              $('.message-input').prop('disabled', false);
              $('#submit12').prop('disabled', false);


              scroll_bottom();
            }, 300);
          }
        });
      }

      function scroll_bottom() {

        $(".messages").animate({
          scrollTop: $('.chat-cover').height()
        }, "fast");
      }

      $(document).on('click', '.submit12', function() {
        newMessage();
      });


      $(window).on('keydown', function(e) {
        // alert('ok');
        if (e.which == 13) {

          newMessage();
          return false;
        }
      });

      // copy li / message and send to input field
      $(document).on('click', '#sending_auto', function() {
        var replyValue = $(this).text()
          .trim(); // fetch the text content of the clicked li element and remove any leading/trailing whitespace
        var str = $(".message-input").val(replyValue);
        newMessage();

      });
    });
    $(document).on('click', '#closebutton', function() {
      $('#chatContainer').fadeToggle().fadeOut();


    });
  </script>
  @if ($auction->auction_length > 0)
  <script>
      var durations = '{{ $diff_d }}d{{ $diff_H }}h{{ $diff_I }}m{{ $diff_S }}s';
      $('.timer-d').timer({
          countdown: true,
          duration: durations,
          format: '%d'
      });
      $('.timer-h').timer({
          countdown: true,
          duration: durations,
          format: '%h'
      });
      $('.timer-m').timer({
          countdown: true,
          duration: durations,
          format: '%m'
      });
      $('.timer-s').timer({
          countdown: true,
          duration: durations,
          format: '%s'
      });
  </script>
@endif
@if (isset($auction->auction_type) && $auction->auction_type == 'Auction Listing')
  <script>
    $(document).ready(function() {
        const auctionEnded = {{ $auction->auction_ended}};
        let createdAt = new Date("{{ $auction->created_at }}").getTime(); 
        let auctionLength = {{ $auction->auction_length }} * 24 * 60 * 60 * 1000;
        let auctionEndTime = createdAt + auctionLength;

        console.log('time', {createdAt, auctionLength, auctionEndTime});

        if(auctionEnded) return;

        console.log('auction_continues');
        function checkAndEndAuction() {
            let now = new Date().getTime();
            if (now >= auctionEndTime) {
                $('#countdown').html("Auction Ended");
                endAuctionAutomatically();
            }
        }

        function endAuctionAutomatically() {
            $.ajax({
                url: "/property/auction/end/{{ $auction->id }}",
                type: "POST",
                headers: {
                    "X-CSRF-TOKEN": "{{ csrf_token() }}"
                },
                success: function (response) {
                    alert(response.message);
                    location.reload();
                },
                error: function (xhr) {
                    console.error("Error:", xhr.responseText);
                }
            });
        }


        checkAndEndAuction();
    })
  </script>
@endif


  {{-- End Message Blade code --}}
@endpush
