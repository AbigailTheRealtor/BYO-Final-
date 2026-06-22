                                <!-- Section Heading -->
                                <h3>Describe your criteria, preferences, and requirements.</h3>
                                <div class="alert alert-info bg-light-info border-info mb-4">
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <strong>📋 Share your criteria and preferences to help interested parties understand exactly what you are looking for.</strong>
                                            <div class="mt-1 text-muted" style="font-weight:normal;">The more details you provide, the better Ask AI can answer questions and the better your listing can be matched with the right rental or agent.</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">Tenant Description</label>
                                    <div class="input-cover">
                                        <textarea wire:model="additional_details" class="form-control" rows="4" style="min-height: 140px; padding: 10px; font-size: 16px;"
                                            x-on:input="$el.closest('.input-cover').nextElementSibling.querySelector('small').textContent = $el.value.length + ' characters'"
                                            placeholder="e.g. Quiet professional looking for a 2-bed apartment near South Tampa. Need pet-friendly unit for one small dog. Budget up to $2,200/mo, prefer 12-month lease."></textarea>
                                    </div>
                                    <div class="text-end mt-1"><small class="text-muted">{{ strlen($additional_details ?? '') }} characters</small></div>
                                </div>

