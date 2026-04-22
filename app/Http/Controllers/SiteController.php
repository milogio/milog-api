<?php

namespace App\Http\Controllers;

use App\Site;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $sites = $request->user()
            ->sites()
            ->get(['sites.id', 'sites.name', 'sites.created_at', 'sites.updated_at', 'sites.published_at']);

        return response()->json($sites);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'published_at' => ['nullable', 'date'],
        ]);

        $site = Site::create($data);

        $request->user()->sites()->attach($site);

        return response()->json($site, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Site  $site
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, Site $site)
    {
        $site = $request->user()
            ->sites()
            ->where('sites.id', $site->id)
            ->firstOrFail();

        return response()->json($site);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Site  $site
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Site $site)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Site  $site
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, Site $site)
    {
        $site = $request->user()
            ->sites()
            ->where('sites.id', $site->id)
            ->firstOrFail();

        $site->delete();

        return response()->noContent();
    }
}
