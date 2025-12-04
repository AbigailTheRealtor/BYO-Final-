<script>
    // all patches
    let moduleName = "{{ $moduleName }}";
    let patchName = "{{ $patchName }}";
    let id = "{{ $id }}";

    let allPatches = <?php echo json_encode(config('listing-patches')); ?>;
    allPatches = allPatches[patchName]['patches']

    let totalPatches = Object.keys(allPatches).length;

    // Create an array of promises for all fetchPatches calls
    let fetchPromises = [];

    showAjaxLoader(true); // Show loader when fetch begins
    $.each(allPatches, function(index, patch) {
        let fetchPromise = fetchPatches(patch, id); // Return the promise from fetchPatches
        fetchPromises.push(fetchPromise); // Add the promise to the array
    });

    // Wait for all fetch requests to complete
    Promise.all(fetchPromises).then(function() {
        console.log('All patches fetched');
        StepWizard.init(); // Initialize StepWizard after all patches are fetched
        // Hide loader after the AJAX request is completed (success or error)
        showAjaxLoader(false);
        changePropertyType(''); // Update property type

        @if (!empty($initializeScripts))
            @foreach ($initializeScripts as $script)
                if (typeof {{ $script }} === "function") {
                    {{ $script }}(); // Call the function if defined
                } else {
                    console.warn("Function {{ $script }} is not defined.");
                }
            @endforeach
            console.log('All scripts initialized.');
        @endif

        $('select').trigger('change');
    }).catch(function(error) {
        console.log("Error fetching patches:", error); // Handle any errors from fetchPatches
    });

    async function fetchPatches(patch, id) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: "{{ route('fetchPatches') }}",
                method: 'POST',
                data: {
                    patch,
                    moduleName,
                    id,
                    _token: "{{ csrf_token() }}"
                },
                cache: false,
                success: function(html) {
                    $('#' + moduleName).append(html.html);
                    resolve(); // Resolve the promise when the request is successful
                },
                error: function(xhr, status, error) {
                    console.log("Error: " + error); // Log any error during the request
                    reject(error); // Reject the promise if there's an error
                },
                complete: function() {
                    
                }
            });
        });
    }
</script>
