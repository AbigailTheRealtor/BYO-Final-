                                <!-- Section Heading -->
                                <h3 class="fw-bold mb-3">Describe the property, features, and highlights.</h3>

                                <div class="alert alert-info bg-light-info border-info mb-4">
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <strong>📋 Provide a description of the property to help interested parties understand its key features, recent improvements, and standout highlights.</strong>
                                            <div class="mt-1 text-muted" style="font-weight:normal;">The more details you provide, the better Ask AI can answer tenant questions and the better your listing can be matched with the right renter.</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">Rental Description</label>
                                    <div class="input-cover">
                                        <textarea wire:model="additional_details" class="form-control" rows="4" style="min-height: 140px; padding: 10px; font-size: 16px;"
                                            x-on:input="document.getElementById('landlord-desc-counter').textContent = $el.value.length + ' characters'"
                                            placeholder="{{ \App\Helpers\PropertyTypePlaceholderHelper::placeholder('landlord', 'create', $property_type) }}"></textarea>
                                    </div>
                                    <div class="text-end mt-1"><small class="text-muted" id="landlord-desc-counter">{{ strlen($additional_details ?? '') }} characters</small></div>
                                </div>

