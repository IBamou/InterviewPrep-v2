<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Concept;
use App\Models\ConceptRelation;
use App\Models\Domain;
use Illuminate\Http\Request;

class ConceptRelationController extends Controller
{
    public function index(Domain $domain, Concept $concept)
    {
        $this->authorize('view', $domain);
        $this->authorize('view', $concept);

        $relations = ConceptRelation::where('concept_id', $concept->id)
            ->with('relatedConcept:id,title')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'type' => $r->type,
                'description' => $r->description,
                'related_concept' => [
                    'id' => $r->relatedConcept->id,
                    'title' => $r->relatedConcept->title,
                ],
            ]);

        return response()->json(['data' => $relations]);
    }

    public function store(Domain $domain, Concept $concept, Request $request)
    {
        $this->authorize('view', $domain);
        $this->authorize('update', $concept);

        $data = $request->validate([
            'related_concept_id' => ['required', 'integer', 'exists:concepts,id'],
            'type' => ['required', 'string', 'in:depends_on,required_by,related_to,alternative_to,similar_to'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        abort_if(
            $data['related_concept_id'] === $concept->id,
            422,
            'A concept cannot relate to itself.',
        );

        $relation = ConceptRelation::create([
            'concept_id' => $concept->id,
            'related_concept_id' => $data['related_concept_id'],
            'type' => $data['type'],
            'description' => $data['description'] ?? null,
        ]);

        return response()->json(['data' => $relation], 201);
    }

    public function destroy(Domain $domain, Concept $concept, ConceptRelation $relation)
    {
        $this->authorize('view', $domain);
        $this->authorize('update', $concept);

        abort_if($relation->concept_id !== $concept->id, 404);

        $relation->delete();

        return response()->json(['message' => 'Relation deleted.']);
    }

    public function suggest(Domain $domain, Concept $concept)
    {
        $this->authorize('view', $domain);
        $this->authorize('view', $concept);

        $otherConcepts = $domain->concepts()
            ->where('id', '!=', $concept->id)
            ->where('user_id', auth()->id())
            ->pluck('title', 'id');

        $existingRelated = ConceptRelation::where('concept_id', $concept->id)
            ->pluck('related_concept_id')
            ->toArray();

        $suggestions = [];

        foreach ($otherConcepts as $id => $title) {
            if (in_array($id, $existingRelated)) {
                continue;
            }

            $titleWords = explode(' ', strtolower($title));
            $conceptWords = explode(' ', strtolower($concept->title));
            $common = array_intersect($titleWords, $conceptWords);

            $confidence = count($common) / max(count($titleWords), count($conceptWords));

            if ($confidence > 0 || count($suggestions) < 3) {
                $suggestions[] = [
                    'concept_id' => $id,
                    'title' => $title,
                    'suggested_type' => 'related_to',
                    'confidence' => round($confidence, 2),
                ];
            }
        }

        usort($suggestions, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        return response()->json(['data' => array_slice($suggestions, 0, 5)]);
    }
}
