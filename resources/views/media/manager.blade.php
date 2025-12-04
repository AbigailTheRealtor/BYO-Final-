<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
    <link rel="stylesheet" href="{{ asset('assets/bootstrap-5.2.2/css/bootstrap.min.css') }}" />
    <script src="https://unpkg.com/dropzone@6.0.0-beta.1/dist/dropzone-min.js"></script>
    <link href="https://unpkg.com/dropzone@6.0.0-beta.1/dist/dropzone.css" rel="stylesheet" type="text/css" />

    <style>
        body {
            margin-top: 20px;
            background: #eee;
        }

        .file-box {
            width: 220px;
        }

        .file-manager h5 {
            text-transform: uppercase;
        }

        .file-manager {
            list-style: none outside none;
            margin: 0;
            padding: 0;
        }

        .folder-list li a {
            color: #666666;
            display: block;
            padding: 5px 0;
        }

        .folder-list li {
            border-bottom: 1px solid #e7eaec;
            display: block;
        }

        .folder-list li i {
            margin-right: 8px;
            color: #3d4d5d;
        }

        .category-list li a {
            color: #666666;
            display: block;
            padding: 5px 0;
        }

        .category-list li {
            display: block;
        }

        .category-list li i {
            margin-right: 8px;
            color: #3d4d5d;
        }

        .category-list li a .text-navy {
            color: #1ab394;
        }

        .category-list li a .text-primary {
            color: #1c84c6;
        }

        .category-list li a .text-info {
            color: #23c6c8;
        }

        .category-list li a .text-danger {
            color: #EF5352;
        }

        .category-list li a .text-warning {
            color: #F8AC59;
        }

        .file-manager h5.tag-title {
            margin-top: 20px;
        }

        .tag-list li {
            float: left;
        }

        .tag-list li a {
            font-size: 10px;
            background-color: #f3f3f4;
            padding: 5px 12px;
            color: inherit;
            border-radius: 2px;
            border: 1px solid #e7eaec;
            margin-right: 5px;
            margin-top: 5px;
            display: block;
        }

        .file {
            border: 1px solid #e7eaec;
            padding: 0;
            background-color: #ffffff;
            position: relative;
            margin-bottom: 20px;
            margin-right: 20px;
        }

        .file-manager .hr-line-dashed {
            margin: 15px 0;
        }

        .file .icon,
        .file .image {
            height: 100px;
            overflow: hidden;
        }

        .file .icon {
            padding: 15px 10px;
            text-align: center;
        }

        .file-control {
            color: inherit;
            font-size: 11px;
            margin-right: 10px;
        }

        .file-control.active {
            text-decoration: underline;
        }

        .file .icon i {
            font-size: 70px;
            color: #dadada;
        }

        .file .file-name {
            padding: 10px;
            background-color: #f8f8f8;
            border-top: 1px solid #e7eaec;
        }

        .file-name small {
            color: #676a6c;
        }

        ul.tag-list li {
            list-style: none;
        }

        .corner {
            position: absolute;
            display: inline-block;
            width: 0;
            height: 0;
            line-height: 0;
            border: 0.6em solid transparent;
            border-right: 0.6em solid #f1f1f1;
            border-bottom: 0.6em solid #f1f1f1;
            right: 0em;
            bottom: 0em;
        }

        a.compose-mail {
            padding: 8px 10px;
        }

        .mail-search {
            max-width: 300px;
        }

        .ibox {
            clear: both;
            margin-bottom: 25px;
            margin-top: 0;
            padding: 0;
        }

        .ibox.collapsed .ibox-content {
            display: none;
        }

        .ibox.collapsed .fa.fa-chevron-up:before {
            content: "\f078";
        }

        .ibox.collapsed .fa.fa-chevron-down:before {
            content: "\f077";
        }

        .ibox:after,
        .ibox:before {
            display: table;
        }

        .ibox-title {
            -moz-border-bottom-colors: none;
            -moz-border-left-colors: none;
            -moz-border-right-colors: none;
            -moz-border-top-colors: none;
            background-color: #ffffff;
            border-color: #e7eaec;
            border-image: none;
            border-style: solid solid none;
            border-width: 3px 0 0;
            color: inherit;
            margin-bottom: 0;
            padding: 14px 15px 7px;
            min-height: 48px;
        }

        .ibox-content {
            background-color: #ffffff;
            color: inherit;
            padding: 15px 20px 20px 20px;
            border-color: #e7eaec;
            border-image: none;
            border-style: solid solid none;
            border-width: 1px 0;
        }

        .ibox-footer {
            color: inherit;
            border-top: 1px solid #e7eaec;
            font-size: 90%;
            background: #ffffff;
            padding: 10px 15px;
        }

        a:hover {
            text-decoration: none;
        }
    </style>
</head>

<body>

    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#myModal">
        Open modal
      </button>



      <div class="my-dropzone" style="width: 500px; height:300px;"></div>
    <!-- The Modal -->
<div class="modal" id="myModal">
    <div class="modal-dialog modal-fullscreen">
      <div class="modal-content">

        <!-- Modal Header -->
        <div class="modal-header mb-0 pb-0">
            <ul class="nav nav-tabs">
                <li class="nav-item">
                  <a class="nav-link" data-bs-toggle="tab" href="#mediaLibraryUploader">Upload New Media</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link active library-tab" data-bs-toggle="tab" href="#mediaLibraryPicker">Pick From library</a>
                </li>
              </ul>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <!-- Modal body -->
        <div class="modal-body" style="overflow-y: auto;">


              <!-- Tab panes -->
            <div class="tab-content">
                <div class="tab-pane container fade" id="mediaLibraryUploader">
                    <div class="card">
                        <div class="card-header text-center">
                            <h5>Upload File</h5>
                        </div>

                        <div class="card-body">
                            <div id="upload-container" class="text-center">
                                <button id="browseFile" class="btn btn-primary">Brows File</button>
                            </div>
                            <div  style="display: none" class="progress mt-3" style="height: 25px">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100" style="width: 75%; height: 100%">75%</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane container active" id="mediaLibraryPicker">
                    <div class="row">
                        <div class="col-lg-12 d-flex flex-wrap justify-content-between">



                            <div class="file-box" style="">
                                <div class="file" style="cursor: pointer;position: relative;">
                                    <input type="checkbox" name="file[]" class="library-file-chk" style="width:100%; height:100%; position: absolute; z-index: 99; opacity:0;" val="">
                                    <a>
                                        <span class="corner"></span>
                                        <div class="icon">
                                            <i class="fa fa-file"></i>
                                        </div>
                                        <div class="file-name">
                                            Document_2014.doc
                                            <br>
                                            <small>Added: Jan 11, 2014</small>
                                        </div>
                                    </a>
                                </div>
                            </div>

                            <div class="file-box">
                                <div class="file">
                                    <a href="#">
                                        <span class="corner"></span>
                                        <div class="image">
                                            <img alt="image" class="img-responsive"
                                                src="http://lorempixel.com/400/300/nature/1">
                                        </div>
                                        <div class="file-name">
                                            Italy street.jpg
                                            <br>
                                            <small>Added: Jan 6, 2014</small>
                                        </div>
                                    </a>
                                </div>
                            </div>
                            <div class="file-box">
                                <div class="file">
                                    <a href="#">
                                        <span class="corner"></span>

                                        <div class="image">
                                            <img alt="image" class="img-responsive"
                                                src="http://lorempixel.com/400/300/nature/2">
                                        </div>
                                        <div class="file-name">
                                            My feel.png
                                            <br>
                                            <small>Added: Jan 7, 2014</small>
                                        </div>
                                    </a>
                                </div>
                            </div>
                            <div class="file-box">
                                <div class="file">
                                    <a href="#">
                                        <span class="corner"></span>

                                        <div class="icon">
                                            <i class="fa fa-music"></i>
                                        </div>
                                        <div class="file-name">
                                            Michal Jackson.mp3
                                            <br>
                                            <small>Added: Jan 22, 2014</small>
                                        </div>
                                    </a>
                                </div>
                            </div>
                            <div class="file-box">
                                <div class="file">
                                    <a href="#">
                                        <span class="corner"></span>

                                        <div class="image">
                                            <img alt="image" class="img-responsive"
                                                src="http://lorempixel.com/400/300/nature/3">
                                        </div>
                                        <div class="file-name">
                                            Document_2014.doc
                                            <br>
                                            <small>Added: Fab 11, 2014</small>
                                        </div>
                                    </a>
                                </div>
                            </div>
                            <div class="file-box">
                                <div class="file">
                                    <a href="#">
                                        <span class="corner"></span>

                                        <div class="icon">
                                            <i class="img-responsive fa fa-film"></i>
                                        </div>
                                        <div class="file-name">
                                            Monica's birthday.mpg4
                                            <br>
                                            <small>Added: Fab 18, 2014</small>
                                        </div>
                                    </a>
                                </div>
                            </div>
                            <div class="file-box">
                                <a href="#">
                                    <div class="file">
                                        <span class="corner"></span>

                                        <div class="icon">
                                            <i class="fa fa-bar-chart-o"></i>
                                        </div>
                                        <div class="file-name">
                                            Annual report 2014.xls
                                            <br>
                                            <small>Added: Fab 22, 2014</small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="file-box">
                                <div class="file">
                                    <a href="#">
                                        <span class="corner"></span>

                                        <div class="icon">
                                            <i class="fa fa-file"></i>
                                        </div>
                                        <div class="file-name">
                                            Document_2014.doc
                                            <br>
                                            <small>Added: Jan 11, 2014</small>
                                        </div>
                                    </a>
                                </div>

                            </div>
                            <div class="file-box">
                                <div class="file">
                                    <a href="#">
                                        <span class="corner"></span>

                                        <div class="image">
                                            <img alt="image" class="img-responsive"
                                                src="http://lorempixel.com/400/300/nature/4">
                                        </div>
                                        <div class="file-name">
                                            Italy street.jpg
                                            <br>
                                            <small>Added: Jan 6, 2014</small>
                                        </div>
                                    </a>

                                </div>
                            </div>
                            <div class="file-box">
                                <div class="file">
                                    <a href="#">
                                        <span class="corner"></span>

                                        <div class="image">
                                            <img alt="image" class="img-responsive"
                                                src="http://lorempixel.com/400/300/nature/5">
                                        </div>
                                        <div class="file-name">
                                            My feel.png
                                            <br>
                                            <small>Added: Jan 7, 2014</small>
                                        </div>
                                    </a>
                                </div>
                            </div>
                            <div class="file-box">
                                <div class="file">
                                    <a href="#">
                                        <span class="corner"></span>

                                        <div class="icon">
                                            <i class="fa fa-music"></i>
                                        </div>
                                        <div class="file-name">
                                            Michal Jackson.mp3
                                            <br>
                                            <small>Added: Jan 22, 2014</small>
                                        </div>
                                    </a>
                                </div>
                            </div>
                            <div class="file-box">
                                <div class="file">
                                    <a href="#">
                                        <span class="corner"></span>

                                        <div class="image">
                                            <img alt="image" class="img-responsive"
                                                src="http://lorempixel.com/400/300/nature/6">
                                        </div>
                                        <div class="file-name">
                                            Document_2014.doc
                                            <br>
                                            <small>Added: Fab 11, 2014</small>
                                        </div>
                                    </a>
                                </div>
                            </div>
                            <div class="file-box">
                                <div class="file">
                                    <a href="#">
                                        <span class="corner"></span>

                                        <div class="icon">
                                            <i class="img-responsive fa fa-film"></i>
                                        </div>
                                        <div class="file-name">
                                            Monica's birthday.mpg4
                                            <br>
                                            <small>Added: Fab 18, 2014</small>
                                        </div>
                                    </a>
                                </div>
                            </div>
                            <div class="file-box">
                                <a href="#">
                                    <div class="file">
                                        <span class="corner"></span>

                                        <div class="icon">
                                            <i class="fa fa-bar-chart-o"></i>
                                        </div>
                                        <div class="file-name">
                                            Annual report 2014.xls
                                            <br>
                                            <small>Added: Fab 22, 2014</small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <div class="file-box">
                                <div class="file">
                                    <a href="#">
                                        <span class="corner"></span>

                                        <div class="icon">
                                            <i class="fa fa-file"></i>
                                        </div>
                                        <div class="file-name">
                                            Document_2014.doc
                                            <br>
                                            <small>Added: Jan 11, 2014</small>
                                        </div>
                                    </a>
                                </div>

                            </div>
                            <div class="file-box">
                                <div class="file">
                                    <a href="#">
                                        <span class="corner"></span>

                                        <div class="image">
                                            <img alt="image" class="img-responsive"
                                                src="http://lorempixel.com/400/300/nature/1">
                                        </div>
                                        <div class="file-name">
                                            Italy street.jpg
                                            <br>
                                            <small>Added: Jan 6, 2014</small>
                                        </div>
                                    </a>

                                </div>
                            </div>
                            <div class="file-box">
                                <div class="file">
                                    <a href="#">
                                        <span class="corner"></span>

                                        <div class="image">
                                            <img alt="image" class="img-responsive"
                                                src="http://lorempixel.com/400/300/nature/2">
                                        </div>
                                        <div class="file-name">
                                            My feel.png
                                            <br>
                                            <small>Added: Jan 7, 2014</small>
                                        </div>
                                    </a>
                                </div>
                            </div>
                            <div class="file-box">
                                <div class="file">
                                    <a href="#">
                                        <span class="corner"></span>

                                        <div class="icon">
                                            <i class="fa fa-music"></i>
                                        </div>
                                        <div class="file-name">
                                            Michal Jackson.mp3
                                            <br>
                                            <small>Added: Jan 22, 2014</small>
                                        </div>
                                    </a>
                                </div>
                            </div>
                            <div class="file-box">
                                <div class="file">
                                    <a href="#">
                                        <span class="corner"></span>

                                        <div class="image">
                                            <img alt="image" class="img-responsive"
                                                src="http://lorempixel.com/400/300/nature/3">
                                        </div>
                                        <div class="file-name">
                                            Document_2014.doc
                                            <br>
                                            <small>Added: Fab 11, 2014</small>
                                        </div>
                                    </a>
                                </div>
                            </div>
                            <div class="file-box">
                                <div class="file">
                                    <a href="#">
                                        <span class="corner"></span>

                                        <div class="icon">
                                            <i class="img-responsive fa fa-film"></i>
                                        </div>
                                        <div class="file-name">
                                            Monica's birthday.mpg4
                                            <br>
                                            <small>Added: Fab 18, 2014</small>
                                        </div>
                                    </a>
                                </div>
                            </div>
                            <div class="file-box">
                                <a href="#">
                                    <div class="file">
                                        <span class="corner"></span>

                                        <div class="icon">
                                            <i class="fa fa-bar-chart-o"></i>
                                        </div>
                                        <div class="file-name">
                                            Annual report 2014.xls
                                            <br>
                                            <small>Added: Fab 22, 2014</small>
                                        </div>
                                    </div>
                                </a>
                            </div>

                        </div>
                    </div>
                </div>
            </div>



        </div>

        <!-- Modal footer -->
        <div class="modal-footer">
          <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
        </div>

      </div>
    </div>
  </div>






<script src="https://kit.fontawesome.com/d7dd5c0801.js" crossorigin="anonymous"></script>
<script src="{{ asset('assets/js/jquery-3.6.1.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/resumablejs@1.1.0/resumable.min.js"></script>
{{-- <script src="{{ asset('assets/bootstrap-5.2.2/js/bootstrap.bundle.min.js') }}"></script> --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Dropzone has been added as a global variable.
    const dropzone = new Dropzone(".dropzone", { url: "{{route('media.upload')}}" });
    /* Dropzone.options.dropzone =
         {
	        maxFiles: 5,
            maxFilesize: 4,
            //~ renameFile: function(file) {
                //~ var dt = new Date();
                //~ var time = dt.getTime();
               //~ return time+"-"+file.name;    // to rename file name but i didn't use it. i renamed file with php in controller.
            //~ },
            acceptedFiles: ".jpeg,.jpg,.png,.gif",
            addRemoveLinks: true,
            timeout: 50000,
            init:function() {

				// Get images
				var myDropzone = this;
				$.ajax({
					url: gallery,
					type: 'GET',
					dataType: 'json',
					success: function(data){
					//console.log(data);
					$.each(data, function (key, value) {

						var file = {name: value.name, size: value.size};
						myDropzone.options.addedfile.call(myDropzone, file);
						myDropzone.options.thumbnail.call(myDropzone, file, value.path);
						myDropzone.emit("complete", file);
					});
					}
				});
			},
            removedfile: function(file)
            {
				if (this.options.dictRemoveFile) {
				  return Dropzone.confirm("Are You Sure to "+this.options.dictRemoveFile, function() {
					if(file.previewElement.id != ""){
						var name = file.previewElement.id;
					}else{
						var name = file.name;
					}
					//console.log(name);
					$.ajax({
						headers: {
							  'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
							  },
						type: 'POST',
						url: delete_url,
						data: {filename: name},
						success: function (data){
							alert(data.success +" File has been successfully removed!");
						},
						error: function(e) {
							console.log(e);
						}});
						var fileRef;
						return (fileRef = file.previewElement) != null ?
						fileRef.parentNode.removeChild(file.previewElement) : void 0;
				   });
			    }
            },

            success: function(file, response)
            {
				file.previewElement.id = response.success;
				//console.log(file);
				// set new images names in dropzone’s preview box.
                var olddatadzname = file.previewElement.querySelector("[data-dz-name]");
				file.previewElement.querySelector("img").alt = response.success;
				olddatadzname.innerHTML = response.success;
            },
            error: function(file, response)
            {
               if($.type(response) === "string")
					var message = response; //dropzone sends it's own error messages in string
				else
					var message = response.message;
				file.previewElement.classList.add("dz-error");
				_ref = file.previewElement.querySelectorAll("[data-dz-errormessage]");
				_results = [];
				for (_i = 0, _len = _ref.length; _i < _len; _i++) {
					node = _ref[_i];
					_results.push(node.textContent = message);
				}
				return _results;
            }

};*/
  </script>

{{--
    ['jpg','png','jpeg','gif','svg','csv','txt','xlx','xls','pdf','doc','docs','docm','docx','dot','dotm','dotx','odt','rtf','wps','xml','xps'];//csv,txt,xlx,xls,pdf
    ['mp4','mov','wmv','avi','mkv','mpeg-2'];
    ['mp3','wav','voc','ogg','oga','cda','ogv'];
    --}}
<script type="text/javascript">
    let browseFile = $('#browseFile');
    let resumable = new Resumable({
        target: '{{ route('media.upload') }}',
        query:{_token:'{{ csrf_token() }}'} ,// CSRF token
        fileType: ['jpg','png','jpeg','gif','svg','csv','txt','xlx','xls','pdf','doc','docs','docm','docx','dot','dotm','dotx','odt','rtf','wps','xml','xps','mp4','mov','wmv','avi','mkv','mpeg-2','mp3','wav','voc','ogg','oga','cda','ogv','zip','rar'],
        chunkSize: 10*1024*1024, // default is 1*1024*1024, this should be less than your maximum limit in php.ini
        headers: {
            'Accept' : 'application/json'
        },
        testChunks: false,
        throttleProgressCallbacks: 1,
    });

    resumable.assignBrowse(browseFile[0]);

    resumable.on('fileAdded', function (file) { // trigger when file picked
        showProgress();
        resumable.upload() // to actually start uploading.
    });

    resumable.on('fileProgress', function (file) { // trigger when file progress update
        updateProgress(Math.floor(file.progress() * 100));
    });

    resumable.on('fileSuccess', function (file, response) { // trigger when file upload complete
        console.log(response);
        response = JSON.parse(response);
        console.log("Response:",response);
        if(response.success)
        {
            $('.progress-bar').addClass('bg-success');
            $('.progress-bar').html('done');
            $('.library-tab').click();
        }
    });

    resumable.on('fileError', function (file, response) { // trigger when there is any error
        alert('file uploading error.')
    });


    let progress = $('.progress');
    function showProgress() {
        progress.find('.progress-bar').css('width', '0%');
        progress.find('.progress-bar').html('0%');
        progress.find('.progress-bar').removeClass('bg-success');
        progress.show();
    }

    function updateProgress(value) {
        progress.find('.progress-bar').css('width', `${value}%`)
        progress.find('.progress-bar').html(`${value}%`)
        /* if(value==100)
        {
            progress.find('.progress-bar').addClass('bg-success');
            progress.find('.progress-bar').html(`done`);
        } */
    }

    function hideProgress() {
        progress.hide();
    }
</script>
<script>
    $(function () {
        $('.library-file-chk').on('change', function(){
            // alert("test");
            if ($(this).is(':checked')) {
                $(this).parent().css('border', '5px solid #000');
            }
            else {
                $(this).parent().css('border', '5px solid #FFF');
            }
        });
    });
</script>

</body>

</html>
