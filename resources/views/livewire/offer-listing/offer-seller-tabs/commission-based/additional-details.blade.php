                                <!-- Section Heading -->
                                <h3>Describe the property, features, and highlights.</h3>
                                <div class="alert alert-info bg-light-info border-info mb-4">
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <strong>📋 Provide a description of the property to help interested parties understand its key features, recent improvements, and standout highlights.</strong>
                                            <div class="mt-1 text-muted" style="font-weight:normal;">The more details you provide, the better Ask AI can answer visitor questions and the better your listing can be matched with the right buyer.</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                <label class="fw-bold">Property Description</label>
                                    <div class="input-cover">
                                        @if ($user_type === 'seller')
                                            @if ($property_type === 'Residential')
                                                <textarea wire:model="additional_details" class="form-control" rows="4" style="min-height: 140px; padding: 10px; font-size: 16px;"
                                                    x-on:input="document.getElementById('seller-desc-counter').textContent = $el.value.length + ' characters'"
                                                    placeholder="e.g. Beautifully updated 3-bed home with open floor plan, updated kitchen, and large backyard. Close to top-rated schools and minutes from I-75. Seller open to quick close."></textarea>
                                            @elseif ($property_type === 'Commercial')
                                                <textarea wire:model="additional_details" class="form-control" rows="4" style="min-height: 140px; padding: 10px; font-size: 16px;"
                                                    x-on:input="document.getElementById('seller-desc-counter').textContent = $el.value.length + ' characters'"
                                                    placeholder="Enter a description of this commercial property, including property class, recent improvements, layout, zoning, and key highlights (e.g., Class A office space, Recent HVAC upgrade, High-traffic location)."></textarea>
                                            @elseif ($property_type === 'Vacant Land')
                                                <textarea wire:model="additional_details" class="form-control" rows="4" style="min-height: 140px; padding: 10px; font-size: 16px;"
                                                    x-on:input="document.getElementById('seller-desc-counter').textContent = $el.value.length + ' characters'"
                                                    placeholder="Enter a description of this vacant land, including lot size, zoning, utilities, access, and development potential (e.g., 2-acre cleared parcel, Residential zoning, Public utilities available)."></textarea>
                                            @else
                                                <textarea wire:model="additional_details" class="form-control" rows="4" style="min-height: 140px; padding: 10px; font-size: 16px;"
                                                    x-on:input="document.getElementById('seller-desc-counter').textContent = $el.value.length + ' characters'"
                                                    placeholder="Enter property description (e.g., Updated 4BR/3BA home with new roof 2022, open floor plan, two-car garage, close to top schools)"></textarea>
                                            @endif
                                        @else
                                            <textarea wire:model="additional_details" class="form-control" rows="4" style="min-height: 140px; padding: 10px; font-size: 16px;"
                                                x-on:input="document.getElementById('seller-desc-counter').textContent = $el.value.length + ' characters'"
                                                placeholder="Enter property description (e.g., Recently renovated kitchen, Waterfront views, Flexible lease terms)"></textarea>
                                        @endif
                                    </div>
                                    <div class="text-end mt-1"><small class="text-muted" id="seller-desc-counter">{{ strlen($additional_details ?? '') }} characters</small></div>
                                </div>

