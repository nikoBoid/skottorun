<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\JournalPage;
use App\Models\JournalTopic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class JournalPageController extends Controller
{
    public function store(Request $request, JournalTopic $journalTopic): RedirectResponse
    {
        $campaign = $this->campaign($request);
        abort_unless($journalTopic->campaign_id === $campaign->id, 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'content' => ['required', 'string', 'max:20000'],
            'occurred_on' => ['nullable', 'date'],
        ]);

        $page = $journalTopic->pages()->create([
            ...$data,
            'author_id' => $request->user()->id,
            'sort_order' => ((int) $journalTopic->pages()->max('sort_order')) + 1,
        ]);

        ActivityLog::record($campaign, $request->user(), 'created', $page, "ha aggiunto «{$page->title}» a {$journalTopic->title}");

        return back()->with('success', 'Pagina aggiunta al diario comune.');
    }

    public function update(Request $request, JournalPage $journalPage): RedirectResponse
    {
        $campaign = $this->campaign($request);
        $journalPage->load('topic');
        abort_unless($journalPage->topic->campaign_id === $campaign->id, 404);

        $journalPage->update($request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'content' => ['sometimes', 'required', 'string', 'max:20000'],
            'occurred_on' => ['sometimes', 'nullable', 'date'],
        ]));

        ActivityLog::record($campaign, $request->user(), 'updated', $journalPage, "ha aggiornato «{$journalPage->title}»");

        return back()->with('success', 'Pagina aggiornata.');
    }

    public function destroy(Request $request, JournalPage $journalPage): RedirectResponse
    {
        $campaign = $this->campaign($request);
        $journalPage->load('topic');
        abort_unless($journalPage->topic->campaign_id === $campaign->id, 404);

        ActivityLog::record($campaign, $request->user(), 'deleted', $journalPage, "ha rimosso «{$journalPage->title}» dal diario");
        $journalPage->delete();

        return back()->with('success', 'Pagina rimossa.');
    }
}
