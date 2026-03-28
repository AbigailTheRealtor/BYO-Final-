@push('styles')
<style>
    .wizard-steps-progress{height:5px;width:100%;background:#CCC;position:absolute;top:0;left:0}
    .steps-progress-percent{height:100%;width:0%;background:#11b7cf}
    .tab-content{padding:20px;border:1px solid #ddd;border-top:none}
    .nav-tabs .nav-link{border:1px solid #ddd;border-bottom:none;margin-right:5px;padding:10px 20px;background:#f8f9fa}
    .nav-tabs .nav-link.active{background:#049399!important;color:#fff!important;border-color:#049399!important}
    .form-group{margin-bottom:15px}.form-group label{font-weight:bold}.form-control{min-height:50px}
    .input-cover{position:relative;display:flex;align-items:center}
    .input-cover .input-icon{position:absolute;left:10px;font-size:25px;color:#11b7cf;pointer-events:none;top:50%;transform:translateY(-50%)}
    .has-icon{padding-left:40px}
    .error{display:block;color:red;font-size:14px;margin-top:5px;width:100%}
    .d-none{display:none}
    .service-section{margin-bottom:1.5rem}
    .section-header{font-size:1rem;border-radius:4px}
    .form-check-label{cursor:pointer}
</style>
@endpush

<div class="container pt-5 pb-5">
    <div class="card">
        <div class="row">
            <div class="col-12 p-4">

                @if (session()->has('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if (session()->has('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <h4 class="mb-4" style="color:#049399;">Counter Agent Bid</h4>

                <form wire:submit.prevent="submit">
                    @php
                        $tabs = [
                            'Broker Compensation & Agency Agreement',
                            'Additional Details',
                            'Offered Services',
                        ];
                    @endphp

                    <ul class="nav nav-tabs" id="sellerCounterTab" role="tablist">
                        @foreach ($tabs as $index => $tab)
                            <li class="nav-item" role="presentation">
                                <button
                                    class="nav-link {{ $activeTab === $index ? 'active' : '' }}"
                                    wire:click.prevent="setActiveTab({{ $index }})"
                                    type="button"
                                    role="tab">
                                    {{ $tab }}
                                </button>
                            </li>
                        @endforeach
                    </ul>

                    <div class="tab-content mt-3">
                        <div class="tab-pane fade {{ $activeTab === 0 ? 'show active' : '' }}">
                            @include('livewire.seller-agent-auction-counter-tabs.broker-compensation')
                        </div>

                        <div class="tab-pane fade {{ $activeTab === 1 ? 'show active' : '' }}">
                            @include('livewire.seller-agent-auction-counter-tabs.additional-details')
                        </div>

                        <div class="tab-pane fade {{ $activeTab === 2 ? 'show active' : '' }}">
                            @include('livewire.seller-agent-auction-counter-tabs.services')
                        </div>
                    </div>

                    <div class="d-flex justify-content-between form-group mt-4">
                        <div>
                            @if ($activeTab > 0)
                                <button type="button" wire:click.prevent="setActiveTab({{ $activeTab - 1 }})"
                                    class="btn btn-secondary">
                                    <i class="fa fa-arrow-left me-1"></i> Previous
                                </button>
                            @endif
                        </div>
                        <div class="d-flex gap-2">
                            @if ($activeTab < count($tabs) - 1)
                                <button type="button" wire:click.prevent="setActiveTab({{ $activeTab + 1 }})"
                                    class="btn btn-primary" style="background:#049399;border-color:#049399;">
                                    Next <i class="fa fa-arrow-right ms-1"></i>
                                </button>
                            @else
                                <button type="submit" class="btn btn-success" id="save-button">
                                    <span wire:loading wire:target="submit">
                                        <span class="spinner-border spinner-border-sm" role="status"></span>
                                        Saving...
                                    </span>
                                    <span wire:loading.remove wire:target="submit">
                                        <i class="fa fa-check me-1"></i>
                                        {{ $counterTermId ? 'Update Counter' : 'Submit Counter' }}
                                    </span>
                                </button>
                            @endif
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
