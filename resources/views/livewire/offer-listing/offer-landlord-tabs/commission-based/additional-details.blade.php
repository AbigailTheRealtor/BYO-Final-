                                <!-- Section Heading -->
                                <h3 class="fw-bold mb-3">Describe the property, features, and highlights.</h3>

                                <div class="alert alert-info bg-light-info border-info mb-4">
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <strong>📋 Provide a description of the property to help interested parties understand its key features, recent improvements, and standout highlights.
                                            </strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">Property Description</label>
                                    <div class="input-cover">
                                        <textarea wire:model="additional_details" class="form-control" rows="4" style="min-height: 140px; padding: 10px; font-size: 16px;"
                                            placeholder="{{ $property_type === 'Commercial Property' ? 'Enter property description (e.g., Modern open floor plan, High-traffic location, ADA-compliant, 2,500 SqFt of leasable space)' : 'Enter property description (e.g., Recently renovated kitchen, Waterfront views, Flexible lease terms, In-unit washer/dryer)' }}"></textarea>
                                    </div>
                                </div>

