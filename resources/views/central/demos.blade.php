@extends('layouts.central', ['banner' => 'Demos',
'help_tag' => App\Dicts\DocsMapping::about_serveronet,
])

@section('content')

<div class="h4 mt-3">Demo Client</div>

<a class="btn btn-outline-primary btn-sm shadow-sm rounded-1 mb-3" target="_blank"
 href="http://client.serveronet.org/">Try on a Demo client ▶</a>
<hr>

<div class="h4 mt-4">Example Sites</div>

<div class="h5 mt-4 font-monospace">SN Social</div>
<div class="">Social Media Site - Allows visitors to create and publish their posts. Also comment and like other visitor's posts. 
    Implemented in Serveronet technology and it's permission model. Visitors have to be granted access before posting. 
    Implemented as an Angular app.</div>
    <div class="fst-italic">Available on a Demo Client</div>
    <a class="btn btn-outline-secondary btn-sm shadow-sm rounded-1 font-monospace" target="_blank"
 href="http://xkuyjxyjn74l2uikred2prikklbh33ajcxo7ixn4gg4eem7xn7mq.client.serveronet.org/">SN Social ▶</a>

<div class="h5 mt-4 font-monospace">Distributed Denial of Secrets</div>
<div class="">Mirror of a popular Leaks Site implemented in Serveronet technology. Distributed Denial of Secrets. Implemented as an React app.</div>
<div class="fst-italic">Available on a Demo Client</div>
<a class="btn btn-outline-secondary btn-sm shadow-sm rounded-1 font-monospace" target="_blank"
 href="http://nldd446sazrflxsmxkgtudwnwykhlvr5dolge3klp3mqfsrycfxa.client.serveronet.org/">DDoSecrets ▶</a>

<div class="h5 mt-4 font-monospace">Tech Demo Site</div>
<div class="">Tech Demo Site which ilustrates most of technical aspects of implementing a website in Serveronet. Vanila html and Vue Js.</div>
<div class="fst-italic">Available on a Demo Client</div>
<a class="btn btn-outline-secondary btn-sm shadow-sm rounded-1 mb-4 font-monospace" target="_blank"
 href="http://hwy4phcbfbigqgr5duodyntqcz34ypr2bdwx6d3oa36rsuvujt7a.client.serveronet.org/">Tech Demo ▶</a>
<hr>
<div class="h4 mt-3">Deployments Scenarios</div>

<div class="h5 mt-3">You can deploy and access Serveronet Sites in multiple ways</div>
<ul class="mt-2 list-unstyled">
    @foreach ($demos as $demo)
    <li class="m-2 p-4 mt-3 border border-light rounded bg-dark bg-gradient overflow-auto">
        <a class="h5 text-decoration-none" href="{{$demo['protocol']}}{{$demo['link']}}{{$demo['highlight']}}" target="_blank">
            <span class="link-secondary opacity-75">{{$demo['protocol']}}</span><span class="link-info">{{$demo['link']}}</span><span class="link-primary">{{$demo['highlight']}}</span>
        </a>
        <div class="mt-3 mb-1">
            <span class="h6 fw-bold text-light">{{$demo['title']}}</span> 
            <span class="h6 text-light"> - {{$demo['description']}}</span>
        </div>
    </li>
    @endforeach
</ul>
@endsection