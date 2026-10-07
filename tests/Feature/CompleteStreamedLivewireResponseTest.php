<?php

use App\Http\Middleware\CompleteStreamedLivewireResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

function streamedLivewireMiddleware(bool $headersSent): CompleteStreamedLivewireResponse
{
    return new class($headersSent) extends CompleteStreamedLivewireResponse
    {
        public function __construct(private bool $headersSent) {}

        protected function headersAlreadySent(): bool
        {
            return $this->headersSent;
        }
    };
}

test('a Livewire response after streaming is sent without re-sending headers', function () {
    $request = Request::create('/livewire/update', 'POST');
    $request->headers->set('X-Livewire', 'true');
    $original = new Response('{"components":[]}', 200);

    $response = streamedLivewireMiddleware(true)->handle($request, fn () => $original);

    expect($response)->not->toBe($original)
        ->and($response->getContent())->toBe('{"components":[]}')
        ->and($response->sendHeaders())->toBe($response);
});

test('ordinary responses are passed through unchanged', function (bool $isLivewire, bool $headersSent) {
    $request = Request::create('/livewire/update', 'POST');
    if ($isLivewire) {
        $request->headers->set('X-Livewire', 'true');
    }
    $original = new Response('ok', 200);

    expect(streamedLivewireMiddleware($headersSent)->handle($request, fn () => $original))->toBe($original);
})->with([
    'livewire without streaming' => [true, false],
    'non-livewire after output' => [false, true],
]);
