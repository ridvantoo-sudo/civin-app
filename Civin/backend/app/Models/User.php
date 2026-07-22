<?php

namespace App\Models;

use App\Features\Users\Models\User as FeatureUser;

/**
 * Compatibility alias; application code uses the feature model.
 */
class User extends FeatureUser {}
