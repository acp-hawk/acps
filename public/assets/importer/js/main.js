function startProcessing() {
    const formData = new FormData($('#frmImport')[0]);
    formData.append('action', 'start'); // Add action to trigger backend process

    // Show the modal and reset progress
    $('#process-modal').show();
    $('#process-text').text('0%'); // Reset progress text
    $('#process-bar').css('width', '0%'); // Reset progress bar

    // Trigger the backend process
    $.ajax({
        url: '/admin/admin_import_proc.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            if (response.success) {
                //ui_add_log('Processing started. Polling progress...');
                pollProcessProgress(response.message); // Start polling
            } else {
                ui_add_log('Processing failed to start: ' + response.message, 'danger');
                $('#process-modal').hide(); // Hide the modal on failure
            }
        },
        error: function (xhr, status, error) {
            ui_add_log('Error starting processing:', 'danger');
            $('#process-modal').hide(); // Hide the modal on error
        },
    });
}
function pollProcessProgress(successMessage) {
    //ui_add_log('Polling process progress...');
    const poll = setInterval(() => {
        $.getJSON('/admin/progress.json', function (progressData) {
            if (!progressData) {
                ui_add_log('No progress data received.', 'danger');
                return;
            }

            const percent = Math.round((progressData.processed / progressData.total) * 100);
            //ui_add_log(`Progress: ${percent}% (${progressData.processed}/${progressData.total})`);

            // Update progress bar and text
            $('#process-bar').css('width', percent + '%');
            $('#process-text').text(`${progressData.processed} of ${progressData.total} files processed (${percent}%)`);

            // Check if processing is complete
            if (percent === 100) {
                clearInterval(poll);
                $('#process-text').css('color', 'green');
                //$('#drag-and-drop-zone').dmUploader('reset');
                ui_add_log(`Finished: `+successMessage, 'success');
                $('#process-finished-text').text(successMessage);
                //alert(successMessage);
                $('#process-modal').hide();
            }
        }).fail(function () {
            ui_add_log('Failed to fetch progress.json.', 'danger');
        });
    }, 1000); // Poll every second
}