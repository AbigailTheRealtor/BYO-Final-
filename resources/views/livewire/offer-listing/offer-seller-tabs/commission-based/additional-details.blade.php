                                <!-- Section Heading -->
                                <h3>Describe the property, features, and highlights.</h3>
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
                                        @if ($user_type === 'seller')
                                            @if ($property_type === 'Residential')
                                                <textarea wire:model="additional_details" class="form-control" rows="4" style="min-height: 140px; padding: 10px; font-size: 16px;"
                                                    placeholder="Enter a description of this residential property, including key features, recent renovations, amenities, and standout highlights (e.g., Newly renovated kitchen, Hardwood floors, Large backyard)."></textarea>
                                            @elseif ($property_type === 'Commercial')
                                                <textarea wire:model="additional_details" class="form-control" rows="4" style="min-height: 140px; padding: 10px; font-size: 16px;"
                                                    placeholder="Enter a description of this commercial property, including property class, recent improvements, layout, zoning, and key highlights (e.g., Class A office space, Recent HVAC upgrade, High-traffic location)."></textarea>
                                            @elseif ($property_type === 'Vacant Land')
                                                <textarea wire:model="additional_details" class="form-control" rows="4" style="min-height: 140px; padding: 10px; font-size: 16px;"
                                                    placeholder="Enter a description of this vacant land, including lot size, zoning, utilities, access, and development potential (e.g., 2-acre cleared parcel, Residential zoning, Public utilities available)."></textarea>
                                            @else
                                                <textarea wire:model="additional_details" class="form-control" rows="4" style="min-height: 140px; padding: 10px; font-size: 16px;"
                                                    placeholder="Enter a detailed description of the property to help interested parties understand its key features, improvements, and standout highlights."></textarea>
                                            @endif
                                        @else
                                            <textarea wire:model="additional_details" class="form-control" rows="4" style="min-height: 140px; padding: 10px; font-size: 16px;"
                                                placeholder="Enter property description (e.g., Recently renovated kitchen, Waterfront views, Flexible lease terms)"></textarea>
                                        @endif
                                    </div>
                                </div>

