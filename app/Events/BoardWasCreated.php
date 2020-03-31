<?php

namespace App\Events;

use App\Board;
use App\Log;
use App\Contracts\Auth\Permittable;
use Illuminate\Queue\SerializesModels;

class BoardWasCreated extends Event
{
    use SerializesModels;

    /**
     * A log name.
     *
     * @var string
     */
    public $action;

    /**
     * Arbitrary log details to be JSON encoded.
     *
     * @var string
     */
    public $actionDetails;

    /**
     * The board the event is being fired on.
     *
     * @var \App\Board
     */
    public $board;

    /**
     * The board the event is being fired on.
     *
     * @var \App\Auth\Permittable
     */
    public $user;

    /**
     * Create a new event instance.
     */
    public function __construct(Board $board, Permittable $user)
    {
        $this->action = "log.board.create";
        $this->actionDetails = null;
        $this->board = $board;
        $this->user = $user;
    }
}
