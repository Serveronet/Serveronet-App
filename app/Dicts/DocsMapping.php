<?php

namespace App\Dicts;

class DocsMapping
{
    public const documentation_index = 'documentation_index';

    public const installation_and_deployment = 'installation_and_deployment';
    
    public const advanced_deployments = 'advanced_deployments';

    public const about_serveronet = 'about_serveronet';

    public const identities = 'identities';

    public const about_serveronet_sites = 'about_serveronet_sites';

    public const sites_development = 'sites_development';

    public const settings = 'settings';

    public const client_administration = 'client_administration';

    public const mapping = [
        self::documentation_index => ['label' => 'Documentation Index', 'description' 
        => 'Documentation Index'],
        self::installation_and_deployment => ['label' => 'Installation and Deployment', 'description' 
        => 'Instructions on Installation and Deployment of Serveronet client on your PC'],
        self::advanced_deployments => ['label' => 'Advanced Deployments', 'description' 
        => 'Instructions on Advanced and non-standard Deployments of Serveronet client like your a Server, Shared Hosting or a Phone'],
        self::about_serveronet => ['label' => 'About Serveronet', 'description' 
        => 'What is Serveronet and how does it work?'],
        self::identities => ['label' => 'Identities', 'description' 
        => 'Cryptographic identities in Serveronet.'],
        self::about_serveronet_sites => ['label' => 'About Serveronet Sites', 'description' 
        => 'What are Serveronet Sites?'],
        self::sites_development => ['label' => 'Sites Development - Developer guidelines', 'description' 
        => 'Serveronet Sites Development - Developer guidelines for most popular framework. Good practicies and limitations.'],
        self::settings => ['label' => 'Settings', 'description' 
        => 'Client Settings'],
        self::client_administration => ['label' => 'Client Administration', 'description' 
        => 'Client Administration'], 
    ];
}
