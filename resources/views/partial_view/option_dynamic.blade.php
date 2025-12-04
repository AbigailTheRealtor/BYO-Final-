{{--

<select name="state" id="state" class="form-control search_places">
    <option value=""> Select State</option>
    @foreach ($statesArray as $state)
    <option value="{{ $state['name'] }}">{{  $state['name']}}</option>
    @endforeach
</select> --}}



<input type="text" name="state[]" id="read_only_state" class="form-control search_places" placeholder="Search Country..."
    data-msg-required="Please enter county" required>
<div class="dropdown" style="width: 1078px;
position: absolute;
left: 51px;
top: 300px;">

    <ul class="dropdown-menu dropdown-menu-right" aria-labelledby="stateDropdownBUTTON" id="stateDropdown">
        <!-- Dropdown options will be populated dynamically -->
    </ul>
</div>
