<?php

namespace App\Http\Controllers;

use App\Models\MediaLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\AbstractHandler;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function manager()
    {
        // $media = Media::find(1);
        // dd($media->toArray());
        return view('media.manager');
    }

    public function upload(Request $request)
    {
        $receiver = new FileReceiver('file', $request, HandlerFactory::classFromRequest($request));

        if (!$receiver->isUploaded()) {
            // file not uploaded
        }

        $fileReceived = $receiver->receive(); // receive file
        if ($fileReceived->isFinished()) { // file uploading is complete / all chunks are uploaded
            $file = $fileReceived->getFile(); // get file
            $extension = $file->getClientOriginalExtension();
            // $request->file();
            $uuid = (string) Str::uuid();
            $fileName = str_replace('.' . $extension, '', $file->getClientOriginalName()); //file name without extenstion
            $fileName .= '_' . md5(time()) . '.' . $extension; // a unique file name
            // $fileName = $uuid.'.'.$extension;

            // $disk = Storage::disk(config('filesystems.default'));
            // $path = $disk->putFileAs('uploads', $file, $fileName);
            $other = [
                "name" => $file->getClientOriginalName(),
                "size" => $file->getSize(), // returns size in bytes
                "ext" => $extension,
            ];

            $file->move(public_path('uploads'), $fileName);



            // dd($file);
            // delete chunked file
            if(file_exists($file->getPathname()))
            {
                unlink($file->getPathname());
            }

            $ml = new MediaLibrary();
            $ml->user_id = Auth::user()->id;
            $ml->original_name = $other['name'];
            $ml->name = $fileName;
            $ml->size = $other['size'];
            $ml->extension = $extension;
            if($ml->save())
            {
                return response()->json([
                    'success' => true,
                    'path' => asset('uploads/' . $fileName),
                    'filename' => $fileName,
                ]);
            }
        }

        // otherwise return percentage information
        $handler = $fileReceived->handler();
        return [
            'done' => $handler->getPercentageDone(),
            'status' => true,
        ];
    }
}
