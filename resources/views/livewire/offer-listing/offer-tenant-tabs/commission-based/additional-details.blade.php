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
                                    <label class="fw-bold">Why This Rental Matches</label>
                                    <div class="input-cover">
                                        <textarea wire:model="additional_details" class="form-control" rows="4" style="min-height: 140px; padding: 10px; font-size: 16px;"
                                            x-on:input="$el.closest('.input-cover').nextElementSibling.querySelector('small').textContent = $el.value.length + ' characters'"
                                            placeholder="Enter why this rental matches the tenant's criteria (e.g., preferred location, lease terms, pet-friendly, move-in timing)"></textarea>
                                    </div>
                                    <div class="text-end mt-1"><small class="text-muted">{{ strlen($additional_details ?? '') }} characters</small></div>
                                </div>

