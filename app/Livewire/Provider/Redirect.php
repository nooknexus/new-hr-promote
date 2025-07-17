<?php

namespace App\Livewire\Provider;

use Livewire\Component;

class Redirect extends Component
{

    public $client_id ="01951dca-8519-7403-8e49-9c0ff7e570e3";
    public $redirect_uri ="";
    public $response_type ="";
    public $url ='';


    public function mount(){
        $this->redirect_uri = route('provider.callback');
        $this->response_type = "code";
        $this->url = "https://moph.id.th/oauth/redirect?client_id=".$this->client_id."&redirect_uri=".$this->redirect_uri."&response_type=".$this->response_type;
    }


    public function redirectToProvider()
    {
        return $this->redirect($this->url);
    }

    public function render()
    {
        return view('livewire.provider.redirect');
    }
}
