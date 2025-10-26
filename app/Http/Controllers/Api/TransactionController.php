<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    // Create funding intent (returns reference + payload placeholder)
    public function fund(Request $req, $projectId) {
        $req->validate(['amount'=>'required|numeric|min:1']);
        $project = Project::findOrFail($projectId);

        $reference = Str::uuid()->toString();
        $transaction = Transaction::create([
            'user_id' => $req->user() ? $req->user()->id : null,
            'project_id'=> $project->id,
            'amount'=> $req->amount,
            'currency'=> $req->currency ?? 'GHS',
            'gateway'=> $req->gateway ?? 'paystack',
            'reference'=> $reference,
            'status'=>'pending'
        ]);

        // TODO: return gateway-specific payload (checkout url / client token)
        return response()->json([
            'transaction' => $transaction,
            'checkout' => [
                'type' => 'redirect',
                'url'  => 'https://example-gateway/checkout?reference='.$reference
            ]
        ]);
    }

    // Webhook handler (public)
    public function webhook(Request $req, $gateway) {
        // Example: gateway validation should be applied here.
        if ($gateway === 'paystack') {
            // verify signature header: 'x-paystack-signature'
            $signature = $req->header('x-paystack-signature');
            $secret = config('services.paystack.secret'); // set in .env
            if ($signature !== hash_hmac('sha512', $req->getContent(), $secret)) {
                return response()->json(['error'=>'Invalid signature'], 403);
            }
            $payload = $req->all();
            // locate reference depending on payload shape
            $reference = data_get($payload,'data.reference');
            $status = data_get($payload,'data.status'); // 'success' etc
        } elseif ($gateway === 'stripe') {
            // stripe uses webhook signature header 'Stripe-Signature', verify using stripe sdk
            $reference = data_get($req->all(),'data.object.id');
            $status = data_get($req->all(),'data.object.status','pending');
        } else {
            $reference = $req->input('reference');
            $status = $req->input('status','pending');
        }

        $tx = Transaction::where('reference', $reference)->first();
        if (! $tx) return response()->json(['message'=>'Transaction not found'], 404);

        DB::transaction(function() use($tx,$status,$req){
            if ($status === 'success' || $status === 'successful') {
                $tx->status = 'success';
                $tx->meta = $req->all();
                $tx->save();
                // update project funding safely
                $project = $tx->project;
                $project->increment('current_amount', $tx->amount);
                // You may dispatch events here (TransactionSuccessful)
            } else {
                $tx->status = 'failed';
                $tx->meta = $req->all();
                $tx->save();
            }
        });

        return response()->json(['message'=>'Webhook processed']);
    }
}
