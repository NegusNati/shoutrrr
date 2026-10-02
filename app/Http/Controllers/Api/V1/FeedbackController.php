<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\FeedbackController as WebFeedbackController;

/**
 * API surface for the feedback widget — the same report payload the legacy
 * web route accepted (type/message/url/browser plus optional screenshot and
 * diagnostics files), handled by the shared controller and returned as JSON.
 */
class FeedbackController extends WebFeedbackController {}
