                                <!-- Section Heading -->
                                <h3>Describe your criteria, preferences, and requirements.</h3>
                                <div class="alert alert-info bg-light-info border-info mb-4">
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <strong>📋 Share your criteria and preferences to help interested parties understand exactly what you are looking for.
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">Criteria &amp; Preferences</label>
                                    <div class="input-cover">
                                        <textarea wire:model="additional_details" class="form-control" rows="4" style="min-height: 140px; padding: 10px; font-size: 16px;"
                                            placeholder="@if($property_type === 'Commercial Property')Describe your commercial space criteria and preferences (e.g., open floor plan, high foot traffic area, loading dock access, zoning requirements, signage allowance).@elseif($property_type === 'Residential Property')Describe your residential criteria and preferences (e.g., 3-bedroom home, pet-friendly, close to downtown, laundry in-unit, parking included).@else Enter your criteria and preferences (e.g., 3-bedroom home, pet-friendly, close to downtown)@endif"></textarea>
                                    </div>
                                </div>

