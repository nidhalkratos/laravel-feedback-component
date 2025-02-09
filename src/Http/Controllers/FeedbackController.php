<?php

namespace Mydnic\Kustomer\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Mydnic\Kustomer\Events\NewFeedback;

class FeedbackController extends Controller
{
    public function store(Request $request)
    {
        $data = $this->validates($request);

        $feedback = $this->storeFeedback($data, $request);

        $this->dispatchEvent($feedback);

        return response()->json([
            'created' => true
        ], 201);
    }

    protected function validates(Request $request)
    {
        return $request->validate([
            'type' => [
                'required', Rule::in(array_keys(config('kustomer.feedbacks'))),
            ],
            'message' => 'required',
            'viewport' => 'array',
        ]);
    }

    protected function storeFeedback($data, Request $request)
    {
        $feedbackModel = config('kustomer.model');
        $feedback = new $feedbackModel;
        
        $feedback->type = $data['type'];
        $feedback->message = $data['message'];
        $feedback->user_info = $this->gatherUserInfo($request);
        $feedback->save();

        return $feedback;
    }

    protected function gatherUserInfo(Request $request)
    {
        return [
            'url' => $request->server('HTTP_REFERER'),
            'language' => $request->getPreferredLanguage(),
            'agent' => $request->server('HTTP_USER_AGENT'),
            'viewport' => $request->viewport,
            'screenshot' => $this->saveScreenshot($request->screenshot),
            'user_id' => auth()->check() ? auth()->id() : null,
        ];
    }

    protected function dispatchEvent($feedback)
    {
        event(new NewFeedback($feedback));
    }

    protected function saveScreenshot($base64Screenshot = null)
    {
        if ($base64Screenshot and config('kustomer.screenshot')) {
            $image = $base64Screenshot;
            $image = str_replace('data:image/png;base64,', '', $image);
            $image = str_replace(' ', '+', $image);
            $imageName = microtime(true) . Str::random(4) . '.' . 'png';
            
            $disk = config('kustomer.screenshot_disk');
            $storage = $disk ? Storage::disk($disk) : Storage::disk();

            if ($storage->put('screenshots/' . $imageName, base64_decode($image))) {
                return 'screenshots/' . $imageName;
            }
        }

        return null;
    }
}
