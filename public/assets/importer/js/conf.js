$(function(){
  $('#drag-and-drop-zone').dmUploader({ //
    url: '/admin/importer/upload.php',
    maxFileSize: 0, // Unlimited 
    onDragEnter: function(){
      // Happens when dragging something over the DnD area
      this.addClass('active');
    },
    onDragLeave: function(){
      // Happens when dragging something OUT of the DnD area
      this.removeClass('active');
    },
    onInit: function(){
      // Plugin is ready to use
      ui_add_log('Alley Cat Uploader Ready', 'info');
    },
    onComplete: function(){
      // All files in the queue are processed (success or error)
      //ui_add_log('All pending photos imported');
      ui_add_log('All files uploaded. Starting processing...');
      //alert('Okay should send now with token: '+$('#token').val());
      //document.getElementById("frmImport").submit();
      //reset();
      startProcessing(); // Begin backend processing
    },
    onNewFile: function(id, file){
      // When a new file is added using the file selector or the DnD area
      //ui_add_log('New file added #' + id);
      ui_multi_add_file(id, file);
    },
    onBeforeUpload: function(id){
      // about tho start uploading a file
      //ui_add_log('Starting the upload of #' + id);
      ui_multi_update_file_status(id, 'uploading', 'Uploading...');
      ui_multi_update_file_progress(id, 0, '', true);
    },
    onUploadCanceled: function(id) {
      // Happens when a file is directly canceled by the user.
      ui_multi_update_file_status(id, 'warning', 'Canceled by User');
      ui_multi_update_file_progress(id, 0, 'warning', false);
    },
    onUploadProgress: function(id, percent){
      // Updating file progress
      ui_multi_update_file_progress(id, percent);
      //ui_add_log(`File ${id} uploading: ${percent}%`);
    },
    onUploadSuccess: function(id, data){
      // A file was successfully uploaded
      //ui_add_log('Server Response for file #' + id + ': ' + JSON.stringify(data));
      //ui_add_log('Upload of file #' + id + ' COMPLETED', 'success');
      ui_multi_update_file_status(id, 'success', 'Upload Complete');
      ui_multi_update_file_progress(id, 100, 'success', false);
      //ui_add_log(`File ${id} uploaded successfully.`);
    },
    onUploadError: function(id, xhr, status, message){
      ui_multi_update_file_status(id, 'danger', message);
      ui_multi_update_file_progress(id, 0, 'danger', false);  
      ui_add_log('Plugin cant be used here, running Fallback callback', 'danger');
    },
    onFallbackMode: function(){
      // When the browser doesn't support this plugin :(
      ui_add_log(message, 'danger');
    },
    onFileSizeError: function(file){
      ui_add_log('File \'' + file.name + '\' cannot be added: size excess limit', 'danger');
    }
  });
});