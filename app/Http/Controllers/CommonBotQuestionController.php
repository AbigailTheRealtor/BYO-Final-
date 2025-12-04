<?php

namespace App\Http\Controllers;

use App\Models\CommonBotQuestion;
use Illuminate\Http\Request;

class CommonBotQuestionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_data['title'] = "Manage Common Chatbot Questions";
        $page_data['commonBotQuestions'] = CommonBotQuestion::all();
        return view('admin.common-bot-questions', $page_data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        abort(404);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'question' => 'required',
            'answer' => 'required'
        ], [
            'question.required' => 'Question is required!',
            'answer.required' => 'Answer is required!',
        ]);
        $commonBotQuestion = new CommonBotQuestion();
        $commonBotQuestion->question = $request->question;
        $commonBotQuestion->answer = $request->answer;
        $commonBotQuestion->save();
        return redirect()->back()->with('success', 'Question saved successfully!');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\CommonBotQuestion  $commonBotQuestion
     * @return \Illuminate\Http\Response
     */
    public function show(CommonBotQuestion $commonBotQuestion)
    {
        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\CommonBotQuestion  $commonBotQuestion
     * @return \Illuminate\Http\Response
     */
    public function edit(CommonBotQuestion $commonBotQuestion)
    {
        abort(404);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\CommonBotQuestion  $commonBotQuestion
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, CommonBotQuestion $commonBotQuestion)
    {
        $request->validate([
            'question' => 'required',
            'answer' => 'required'
        ], [
            'question.required' => 'Question is required!',
            'answer.required' => 'Answer is required!',
        ]);
        $commonBotQuestion->question = $request->question;
        $commonBotQuestion->answer = $request->answer;
        $commonBotQuestion->update();
        return redirect()->back()->with('success', 'Question updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\CommonBotQuestion  $commonBotQuestion
     * @return \Illuminate\Http\Response
     */
    public function destroy(CommonBotQuestion $commonBotQuestion)
    {
        $commonBotQuestion->delete();
        return redirect()->back()->with('success', 'City deleted successfully');
    }
}
