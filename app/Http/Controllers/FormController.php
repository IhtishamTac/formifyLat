<?php

namespace App\Http\Controllers;

use App\Models\AllowedDomain;
use App\Models\Answer;
use App\Models\Form;
use App\Models\Question;
use App\Models\Response;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FormController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $forms =  Form::where('creator_id', auth()->id())->get();
        return response()->json([
            'message' => 'Get all forms success',
            'forms' => $forms
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'slug' => 'required|unique:forms,slug|regex:/^[a-zA-Z.-]+$/',
            'allowed_domains' => 'array',
            'description' => '',
            'limit_one_response' => ''
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid field',
                'errors' => $validator->errors(),
            ], 422);
        }

        $form = new Form();
        $form->name = $request->name;
        $form->slug = $request->slug;
        $form->description = $request->description;
        $form->limit_one_response = $request->limit_one_response;
        $form->creator_id = auth()->id();
        $form->save();
        if ($form) {
            foreach ($request->allowed_domains as $lor) {
                $ad = new AllowedDomain();
                $ad->form_id = $form->id;
                $ad->domain = $lor;
                $ad->save();
            }
        }
        return response()->json([
            'message' => 'Create form success',
            'form' => $form
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show($slug)
    {
        $form = Form::where('slug', $slug)->with(['allowed_domain', 'questions'])->first();
        if (!$form) return response()->json([
            'message' => 'Form not found'
        ], 404);
        if ($form->creator_id != auth()->id()) return response()->json([
            'message' => 'Forbidden access'
        ], 403);
        $domains = $form->allowed_domain->pluck('domain');
        $form->allowed_domains = $domains;

        $form->makeHidden('allowed_domain');
        return response()->json([
            'message' => 'Get form success',
            'form' => $form
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Form $form)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Form $form)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Form $form)
    {
        //
    }


    //for questions
    public function addQuestion($slug, Request $request)
    {
        $form = Form::where('slug', $slug)->first();
        if (!$form) return response()->json([
            'message' => 'Form not found'
        ], 404);
        if ($form->creator_id != auth()->id()) return response()->json([
            'message' => 'Forbidden access'
        ], 403);

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'choice_type' => 'required|in:short answer,paragraph,date,multiple choice,dropdown,checkboxes',
            'choices' => 'required_if:choice_type,multiple choice,dropdown,checkboxes',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid field',
                'errors' => $validator->errors(),
            ], 422);
        }
        $choicess = '';
        if ($request->choices) {
            if (count($request->choices) > 0) {
                $choicess = implode(', ', $request->choices);
            } else {
                $choicess = $request->choices;
            }
        }
        $question = new Question();
        $question->name = $request->name;
        $question->choice_type = $request->choice_type;
        $question->is_required = $request->is_required || 0;
        $question->choices = $choicess;
        $question->form_id = $form->id;
        $question->save();

        return response()->json([
            'message' => 'Add question success',
            'question' => $question
        ], 200);
    }

    public function removeQuestion($slug, $question_id)
    {
        $form = Form::where('slug', $slug)->first();
        if (!$form) return response()->json([
            'message' => 'Form not found'
        ], 404);
        if ($form->creator_id != auth()->id()) return response()->json([
            'message' => 'Forbidden access'
        ], 403);
        $question = Question::where('id', $question_id)->first();
        if (!$question) return response()->json([
            'message' => 'Question not found'
        ], 404);
        if ($question->delete()) return response()->json(['message' => 'Remove question success'], 200);
    }


    //for responses
    public function addResponse($slug, Request $request)
    {
        $form = Form::where('slug', $slug)->first();
        $questionMin = Question::where(['form_id'=>$form->id])->get();
        $validator = Validator::make($request->all(), [
            'answers' => 'required|array',
            'answers.*.question_id' => ['required', Rule::in($questionMin->pluck('id')->toArray())],
            'answers.*.value'=>'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid field',
                'errors' => $validator->errors(),
            ], 422);
        }

        $required = $questionMin->where('is_required', true)->count();
        foreach ($request->answers as $answer) {
            foreach ($questionMin->where('is_required', true) as $question) {
                if($answer['question_id'] == $question->id){
                    $required--;
                }
            }
        }
        if($required > 0){
            return response()->json(['message' => 'Invalid fields', 'errors' => []], 422);
        }

        $allowedDomains = AllowedDomain::where('form_id', $form->id)->get();
        $domains = $allowedDomains->pluck('domain')->toArray();
        $thisUserDomain = explode('@', $request->user()->email)[1];
        if (!in_array($thisUserDomain, $domains)) {
            return response()->json([
                'message' => 'Forbidden access'
            ], 403);
        }

        if($form->limit_one_response === 1){
            if(Response::where(['user_id' => auth()->id(), 'form_id' => $form->id])->exists()){
                return response()->json([
                    'message' => 'You can not submit twice',
                ], 401);
            }
        }

        if ($request->answers) {
            $response = new Response();
            $response->form_id = $form->id;
            $response->user_id = auth()->id();
            $response->date = Carbon::now();
            $response->save();
            foreach ($request->answers as $answer) {
                $newAnswer = new Answer();
                $newAnswer->response_id = $response->id;
                $newAnswer->question_id = $answer['question_id'];
                $newAnswer->value = $answer['value'];
                $newAnswer->save();
            }
        }
        return response()->json([
            'message' => 'Submit response success',
        ], 200);
    }

    public function getResponses($slug)
    {
        $form = Form::where('slug', $slug)->first();
        if (!$form) return response()->json([
            'message' => 'Form not found'
        ], 404);
        if ($form->creator_id != auth()->id()) return response()->json([
            'message' => 'Forbidden access'
        ], 403);

        $responses = Response::where('form_id', $form->id)->with(['user', 'answer'])->get();
        $questionArray = Question::where('form_id', $form->id)->get();
        $questionName = $questionArray->pluck('name');
        if ($questionName->count() <= 0) {
            return;
        }
        foreach ($questionName as $qn) {
            $keyNames[] = $qn;
        }

        $newResponses = [];
        foreach ($responses as $res) {
            $newAnswers = [];
            foreach ($res->answer as $index => $answer) {
                $newAnswers[$keyNames[$index]] = $answer['value'];
            }

            $newResponses[] = [
                'date' => $res->date,
                'user' => $res->user,
                'answers' => $newAnswers
            ];
        }

        return response()->json([
            'message' => 'Get responses success',
            'responses' => $newResponses
        ], 200);
    }
}
