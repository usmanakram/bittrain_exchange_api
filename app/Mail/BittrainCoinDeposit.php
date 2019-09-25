<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class BittrainCoinDeposit extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // return $this->view('view.name');

        /*return $this->from('sender@example.com')
                    ->view('mails.demo')
                    ->text('mails.demo_plain')
                    ->with(
                        [
                            'testVarOne' => '1',
                            'testVarTwo' => '2',
                        ]
                    )
                    ->attach(public_path('/images').'/demo.jpg', [
                        'as' => 'demo.jpg',
                        'mime' => 'image/jpeg',
                    ])
                    ;*/

        // return $this->from('usman.akram99@gmail.com')->view('mails.aws_demo');
        return $this->from('usman.akram99@gmail.com')->view('mails.bittrain_coin_deposit');
    }
}
