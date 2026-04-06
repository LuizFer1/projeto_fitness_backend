<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\GoalController;

$user = User::first();
if (!$user) {
    try {
        $user = User::factory()->create();
    } catch (\Throwable $e) {
        $user = User::create([
            'name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'cpf' => '00000000000',
            'password_hash' => bcrypt('password')
        ]);
    }
}

echo "Testing for user: {$user->email}\n";

$controller = new GoalController();

echo "\n--- Update Exercise ---\n";
$req1 = Request::create('/api/goals/exercise', 'PUT', [
    'goal_steps_day' => 10000,
    'goal_workouts_week' => 5
]);
$req1->setUserResolver(fn() => $user);
$res1 = $controller->updateExercise($req1);
echo $res1->getContent() . "\n";

echo "\n--- Update Alimentation ---\n";
$req2 = Request::create('/api/goals/alimentation', 'PUT', [
    'goal_calories_day' => 2500,
    'goal_protein_g' => 180,
    'goal_water_liters' => 3.5
]);
$req2->setUserResolver(fn() => $user);
$res2 = $controller->updateAlimentation($req2);
echo $res2->getContent() . "\n";

echo "\n--- Get Goals ---\n";
$req3 = Request::create('/api/goals', 'GET');
$req3->setUserResolver(fn() => $user);
$res3 = $controller->index($req3);
echo $res3->getContent() . "\n";
