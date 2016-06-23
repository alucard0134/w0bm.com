<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Message;
use App\Models\ModeratorLog;
use App\Models\Video;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

class CommentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $id)
    {
        $user = auth()->check() ? auth()->user() : null;
        $xhr = $request->ajax();

        if(is_null($user)) return $xhr ? "Not logged in" : redirect()->back()->with('error', 'Not logged in');
        if(!$request->has('comment')) return $xhr ? "You need to enter a comment" : redirect()->back()->with('error', 'You need to enter a comment');
        if(mb_strlen(trim($request->get('comment'))) > 1000 ) return $xhr ? "Comment to long" : redirect()->back()->with('error', 'Comment to long');

        $video = Video::findOrFail($id);

        $com = new Comment();
        $com->content = trim($request->get('comment'));
        $com->user()->associate($user);
        $com->video()->associate($video);
        $com->save();

        foreach($com->getMentioned() as $mentioned) {
            Message::send($user->id, $mentioned->id, $user->username . ' mentioned you in a comment', view('messages.commentmention', ['video' => $video, 'user' => $user]));
        }

        foreach($com->answered() as $answered) {
            Message::send($user->id, $answered->id, $user->username . ' answered on your comment', view('messages.commentanswer', ['video' => $video, 'user' => $user]));
        }

        if($user->id != $video->user->id)
            Message::send($user->id, $video->user->id, $user->username . ' commented on your video', view('messages.videocomment', ['video' => $video, 'user' => $user]));

        return $xhr ? view('partials.comment', ['comment' => $com, 'mod' => $user->can('delete_comment')]) : redirect()->back()->with('success', 'Comment successfully saved');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = auth()->check() ? auth()->user() : null;
        if(is_null($user)) return redirect()->back()->with('error', 'Not logged in');

        if($user->can('delete_comment')) {
            Comment::destroy($id);

            $log = new ModeratorLog();
            $log->user()->associate($user);
            $log->type = 'delete';
            $log->target_type = 'comment';
            $log->target_id = $id;
            $log->save();

            return redirect()->back()->with('success', 'Comment deleted');
        }
        return redirect()->back()->with('error', 'Insufficient permissions');
    }
    
    public function restore($id)
    {
        $user = auth()->check() ? auth()->user() : null;
        if(is_null($user)) return redirect()->back()->with('error', 'Not logged in');

        if($user->can('delete_comment')) {
            Comment::withTrashed()->whereId($id)->restore();

            $log = new ModeratorLog();
            $log->user()->associate($user);
            $log->type = 'restore';
            $log->target_type = 'comment';
            $log->target_id = $id;
            $log->save();

            return redirect()->back()->with('success', 'Comment restored');
        }
        return redirect()->back()->with('error', 'Insufficient permissions');
    }
}
