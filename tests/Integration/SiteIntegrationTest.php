<?php

use App\Http\Consts;

$tests_domain = env('tests_domain');

if (! $tests_domain) {
    throw new Exception('Missing: $tests_domain', 1);
}

$GLOBALS['domain'] = $tests_domain;
$GLOBALS['site_id'] = '';
$GLOBALS['new_record_random_string'] = 'new_record_random_string';
$ports = ['19081', '19082', '19083'];
// $ports = ['19082'];

$devActors = [
    ['visitor_id' => 'gs4fpzpbfgzxahjsyni3kocrxxhpdy6iqj7uczpbqigeibxs3h6a',
        'alias' => 'Tech Demo Site Admin (Dev)',
        'base64_seed' => 'U6oi1jdKCvSrvWakk0NaRjLZsFpSz4ClGXSA0PkMwUo=',
        'api_token' => 'string1',
    ],

    ['visitor_id' => 'rqljzjfvpclua55ievvdpwh5ooe6jodt53qjjoykwquoe2pvn2ma',
        'alias' => 'Visitor with rights (Dev)',
        'base64_seed' => 'XPj1q+DF/ZyFnFPGAz2H6QbehrHQUDPzkSlVO/RoooI=',
        'api_token' => 'string2',
    ],

    ['visitor_id' => 'a3zr34ae6kz4abkwfs6tgleno6fz2mp7xoxmme5u72juvqcv5anq',
        'alias' => 'Visitor with NO rights (Dev)',
        'base64_seed' => 'kUxOJ1e/5ibuWcLYIjm7LDXpjjYW/eAcpZj4xcoSmeo=',
        'api_token' => 'string3',
    ],
];



foreach ($ports as $key => $port) {
    test('Complete First Run if not already '.$port, function () use ($port) {
        dump('Test Name: '.$this->name());

        $page = visit("http://{$GLOBALS['domain']}:{$port}/");
        $isFirstRunCompleted = ! Str::contains($page->url(), 'first_run_setup');

        if (! $isFirstRunCompleted) {            
            $page->check('isDevNode');
            $initialTrackers = [
                "http://{$GLOBALS['domain']}:19083/announce",
            ];
            $initialTrackersJson = json_encode($initialTrackers);
            $page->type('input[name=initialTrackersJson]', $initialTrackersJson);
            $page->press('Complete the First Run');
        }
        retry(10, function () use (&$page) {
            $page->assertSee('Welcome to the Serveronet Client');

        }, 1000);
        $page->wait(2);
        $page = null;
    })->group('Integration');
}

foreach ($ports as $key => $port) {
    test('Update Instance '.$port, function () use ($port) {
        dump('Test Name: '.$this->name());
        loginAsAdmin($page = visit("http://{$GLOBALS['domain']}:{$port}/"));
        $page->navigate("http://{$GLOBALS['domain']}:{$port}/admin/advanced_control_panel");
        $page->click('Update');
        retry(10, function () use (&$page) {
            $page->assertSee('Current version:');
        }, 1000);
        $page = null;
    })->group('Integration');
}

foreach ($ports as $key => $port) {
    foreach ($devActors as $key => $devActor) {

        test('Register '.$devActor['alias'].' on '.$port, function () use ($port, $devActor) {
            dump('Test Name: '.$this->name());

            $page = visit("http://visitor-control-panel.{$GLOBALS['domain']}:$port/register");
            $page->type('input[name=base64_seed]', $devActor['base64_seed']);
            $page->type('input[name=alias]', $devActor['alias']);
            $page->type('input[name=password]', Consts::passwordForTests);
            $page->type('input[name=password_confirmation]', Consts::passwordForTests);
            $page->wait(1);
            $page->submit();
            $page->assertSee('Your Identity is');

        })->group('Integration');
    }
}

test('Publish Site on Site Owner Node', function () use ($devActors) {
    dump('Test Name: '.$this->name());

    loginAsAdmin($page = visit("http://{$GLOBALS['domain']}:19081/"));

    $page->navigate("http://{$GLOBALS['domain']}:19081/admin/developed_sites");
    $assigned_site_id = $page->value('input[id=assigned_site_id]');
    dump('assigned_site_id: '.$assigned_site_id);
    $GLOBALS['site_id'] = $assigned_site_id;
    $value = $page->value('textarea[id=texarea_'.$assigned_site_id.']');

    $obj = json_decode($value);
    $obj->site_Admin_Signers = $devActors[0]['visitor_id'];
    $asJson = json_encode($obj, JSON_PRETTY_PRINT);
    $page->type('textarea[id=texarea_'.$assigned_site_id.']', $asJson);

    $title = date('Y-m-d H:i:s').' Tech-Demo-Site Tests';
    $page->type('input[name=title]', $title);
    $page->click('Publish Site 📢');
    $page->assertSee('Publishing Site');

    retry(10, function () use (&$page) {
        $page->assertSee('Site published:');
    }, 1000);

})->group('Integration');

test('retrieve TD Site on Peer Node', function () {
    dump('Test Name: '.$this->name());

    $page = visit("http://{$GLOBALS['site_id']}.{$GLOBALS['domain']}:19082/");
    retry(10, function () use (&$page) {
        $page->navigate("http://{$GLOBALS['site_id']}.{$GLOBALS['domain']}:19082/");
        $page->assertSee('This is a sample web site showing how Sites behave in the Serveronet');
    }, 2000);

})->group('Integration');

test('retrieve TD Site on Leech Node not to be hosted', function () {
    dump('Test Name: '.$this->name());

    $page = visit("http://{$GLOBALS['site_id']}.{$GLOBALS['domain']}:19083/?is_to_be_hosted_override=0");
    retry(10, function () use (&$page) {
        $page->navigate("http://{$GLOBALS['site_id']}.{$GLOBALS['domain']}:19083/?is_to_be_hosted_override=0");
        $page->assertSee('This is a sample web site showing how Sites behave in the Serveronet');
    }, 2000);

})->group('Integration');

test('Query records on Leech Node', function () {
    dump('Test Name: '.$this->name());

    $page = visit("http://{$GLOBALS['site_id']}.{$GLOBALS['domain']}:19083/");
    $page->press('Show filtered records');
    retry(10, function () use (&$page) {
        $page->press('Show filtered records');
        $page->assertSee('select * from "posts" where');
    }, 3000);

})->group('Integration');

test('Login as Site Admin and post a grant record', function () use ($devActors) {
    dump('Test Name: '.$this->name());

    $page = visit("http://{$GLOBALS['site_id']}.{$GLOBALS['domain']}:19083/login");
    $page->assertSee('Login to the Site:')
        ->type('input[name=visitor_id]', $devActors[0]['visitor_id'])
        ->type('input[name=password]', Consts::passwordForTests)
        ->submit();

    retry(10, function () use (&$page) {
        $page->assertSee('Signer is a Site Admin');
    }, 1000);
    $page->type('input[id=grantee_visitor_id]', $devActors[1]['visitor_id']);
    $page->press('Create Grant Record DB');
    $page->wait(2);
    $page->press('Create Grant Record for Uploades');
    $page->wait(2);
    retry(10, function () use (&$page) {
        $page->assertSee('"success":true');
    }, 1000);

})->group('Integration');

test('Login on Leech Node as Visitor with grant and Post a record', function () use ($devActors) {
    dump('Test Name: '.$this->name());

    $page = visit("http://{$GLOBALS['site_id']}.{$GLOBALS['domain']}:19083/login");
    $page->assertSee('Login to the Site:')
        ->type('input[name=visitor_id]', $devActors[1]['visitor_id'])
        ->type('input[name=password]', Consts::passwordForTests)
        ->submit();
    retry(10, function () use (&$page) {
        $page->assertSee('Grant record for Signer found for Records');
    }, 1000);
    $GLOBALS['new_record_random_string'] = Str::random(5);
    $page->type('input[id=title]', 'New Record by Visitor with Grant '.$GLOBALS['new_record_random_string']);
    $page->press('POST');
    retry(10, function () use (&$page) {
        $page->assertSee(' "success": true, "data": { "_sn_entity_id": "');
    }, 1000);

})->group('Integration');

test('query records for new record string on Peer', function () {
    dump('Test Name: '.$this->name());

    $page = visit("http://{$GLOBALS['site_id']}.{$GLOBALS['domain']}:19082/");
    $page->press('Show filtered records');

    retry(10, function () use (&$page) {
        $page->press('Show filtered records');
        $page->assertSee($GLOBALS['new_record_random_string']);
    }, 3000);

})->group('Integration');

test('query records for new record string on Site Owner Node', function () {
    dump('Test Name: '.$this->name());

    $page = visit("http://{$GLOBALS['site_id']}.{$GLOBALS['domain']}:19081/");
    $page->press('Show filtered records');

    retry(10, function () use (&$page) {
        $page->press('Show filtered records');
        $page->assertSee($GLOBALS['new_record_random_string']);
    }, 3000);

})->group('Integration');

test('query records for new record string on Leech Node', function () {
    dump('Test Name: '.$this->name());

    $page = visit("http://{$GLOBALS['site_id']}.{$GLOBALS['domain']}:19083/");
    $page->press('Show filtered records');

    retry(10, function () use (&$page) {
        $page->press('Show filtered records');
        $page->assertSee($GLOBALS['new_record_random_string']);
    }, 3000);

})->group('Integration');

function loginAsAdmin($page): mixed
{
    $page->navigate($page->url().'admin/dashboard')
        ->assertSee('Client Administrator login')
        ->type('input[name=password]', Consts::passwordForTests)
        ->submit();

    return null;
}
