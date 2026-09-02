<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\JournalTopic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JournalTopicController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $campaign = $this->campaign($request);
        $data = $request->validate([
            'title' => [
                'required',
                'string',
                'max:160',
                Rule::unique('journal_topics', 'title')->where('campaign_id', $campaign->id),
            ],
        ]);

        $topic = $campaign->journalTopics()->create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        ActivityLog::record($campaign, $request->user(), 'created', $topic, "ha aperto l’argomento «{$topic->title}» nel diario comune");

        return back()->with('success', 'Argomento creato. Ora puoi aggiungere la prima pagina.');
    }

    public function destroy(Request $request, JournalTopic $journalTopic): RedirectResponse
    {
        $campaign = $this->campaign($request);
        abort_unless($journalTopic->campaign_id === $campaign->id, 404);

        ActivityLog::record($campaign, $request->user(), 'deleted', $journalTopic, "ha rimosso l’argomento «{$journalTopic->title}» dal diario");
        $journalTopic->delete();

        return back()->with('success', 'Argomento rimosso.');
    }
}
