<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HelpTicket;
use App\Models\HelpTicketReply; 
use App\Models\Notification;
use App\Models\User;
use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HelpController extends Controller
{
    // ─── Customer ──────────────────────────────────────────────────────────────

    // Customer: buat tiket
    public function store(Request $request)
    {
        $request->validate([
            'subject'     => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'category'    => 'nullable|in:Order,Pembayaran,Pengiriman,Traveler,Umum,Lainnya',
            'priority'    => 'nullable|in:low,medium,high',
        ]);

        $ticket = HelpTicket::create([
            'code'        => 'TKT-' . strtoupper(Str::random(6)),
            'customer_id' => $request->user()->id,
            'subject'     => $request->subject,
            'description' => $request->description,
            'category'    => $request->category ?? 'Umum',
            'priority'    => $request->priority ?? 'medium',
            'status'      => 'open',
        ]); 


            // NOTIF KE ADMIN
            $admins = User::where('role', 'admin')->get();

            foreach ($admins as $admin) {

                Notification::create([
                    'user_id' => $admin->id,
                    'type' => 'Help Ticket',
                    'title' => 'Ticket Baru',
                    'message' => json_encode([
                        'ticket_code' => $ticket->code,
                        'customer' => $request->user()->name,
                        'subject' => $ticket->subject,
                        'status' => 'open',
                    ]),
                    'is_read' => false,
                ]);

            }


        return response()->json([
            'success' => true,
            'message' => 'Laporan berhasil dikirim. Tim kami akan menghubungi kamu dalam 1×24 jam.',
            'data'    => [
                'id'   => $ticket->id,
                'code' => $ticket->code,
            ],
        ], 201);
    }

    // Customer: list tiket miliknya
    public function myTickets(Request $request)
    {
        $tickets = HelpTicket::where('customer_id', $request->user()->id)
            ->with('replies')
            ->latest()
            ->get()
            ->map(fn($t) => $this->formatTicket($t));

        return response()->json(['success' => true, 'data' => $tickets]);
    }

    // Customer: list FAQ aktif
    public function faqs(Request $request)
    {
        $faqs = Faq::where('is_active', true)
            ->when($request->filled('category'), fn($q) => $q->where('category', $request->category))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn($f) => [
                'id'       => $f->id,
                'code'     => $f->code,
                'question' => $f->question,
                'answer'   => $f->answer,
                'category' => $f->category,
            ]);

        return response()->json(['success' => true, 'data' => $faqs]);
    }

    // ─── Admin ─────────────────────────────────────────────────────────────────

    // Admin: list semua tiket
    public function adminIndex(Request $request)
    {
        $query = HelpTicket::with(['customer:id,name,email', 'replies'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('subject', 'like', '%' . $request->search . '%')
                  ->orWhereHas('customer', fn($q2) =>
                      $q2->where('name', 'like', '%' . $request->search . '%'));
            });
        }

        $tickets = $query->paginate(20);

        $tickets->getCollection()->transform(fn($t) => $this->formatTicket($t));

        $stats = [
            'open'       => HelpTicket::where('status', 'open')->count(),
            'in_progress'=> HelpTicket::where('status', 'in_progress')->count(),
            'resolved'   => HelpTicket::where('status', 'resolved')->count(),
            'total'      => HelpTicket::count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => $tickets,
            'stats'   => $stats,
        ]);
    }

    // Admin: balas tiket
    public function reply(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $ticket = HelpTicket::findOrFail($id);

        HelpTicketReply::create([
            'ticket_id'   => $ticket->id,
            'user_id'     => $request->user()->id,
            'message'     => $request->message,
            'is_admin'    => true,
            'author_name' => $request->user()->name,
        ]);

        // Update status ke in_progress jika masih open
        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        return response()->json(['success' => true, 'message' => 'Balasan terkirim.']);
    }

    // Admin: selesaikan tiket
    public function resolve(Request $request, $id)
    {
        $ticket = HelpTicket::findOrFail($id);

        $ticket->update([
            'status'      => 'resolved',
            'resolved_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Tiket diselesaikan.']);
    }

    // Admin: CRUD FAQ
    public function adminFaqs()
    {
        $faqs = Faq::orderBy('sort_order')->orderBy('id')->get()
            ->map(fn($f) => [
                'id'         => (string) $f->id,
                'code'       => $f->code,
                'question'   => $f->question,
                'answer'     => $f->answer,
                'category'   => $f->category,
                'sort_order' => $f->sort_order,
                'is_active'  => $f->is_active,
            ]);

        return response()->json(['success' => true, 'data' => $faqs]);
    }

    public function storeFaq(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:500',
            'answer'   => 'required|string|max:2000',
            'category' => 'required|in:Umum,Order,Pembayaran,Pengiriman,Akun',
        ]);

        $faq = Faq::create([
            'code'       => 'FAQ-' . strtoupper(Str::random(6)),
            'question'   => $request->question,
            'answer'     => $request->answer,
            'category'   => $request->category,
            'sort_order' => Faq::max('sort_order') + 1,
            'is_active'  => true,
        ]);

        return response()->json(['success' => true, 'message' => 'FAQ ditambahkan.', 'data' => $faq], 201);
    }

    public function updateFaq(Request $request, $id)
    {
        $request->validate([
            'question' => 'required|string|max:500',
            'answer'   => 'required|string|max:2000',
            'category' => 'required|in:Umum,Order,Pembayaran,Pengiriman,Akun',
        ]);

        $faq = Faq::findOrFail($id);
        $faq->update($request->only(['question', 'answer', 'category']));

        return response()->json(['success' => true, 'message' => 'FAQ diperbarui.']);
    }

    public function destroyFaq($id)
    {
        Faq::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'FAQ dihapus.']);
    }

    // ─── Helper ────────────────────────────────────────────────────────────────

    private function formatTicket(HelpTicket $t): array
    {
        return [
            'id'          => (string) $t->id,
            'code'        => $t->code,
            'subject'     => $t->subject,
            'description' => $t->description ?? '',
            'customer'    => $t->customer?->name ?? 'Anonim',
            'email'       => $t->customer?->email ?? '',
            'status'      => $t->status,
            'priority'    => $t->priority,
            'category'    => $t->category,
            'date'        => $t->created_at->format('d M Y'),
            'resolvedAt'  => $t->resolved_at?->format('d M Y, H:i'),
            'replies'     => $t->replies->map(fn($r) => [
                'author'   => $r->author_name,
                'message'  => $r->message,
                'time'     => $r->created_at->format('d M, H:i'),
                'isAdmin'  => $r->is_admin,
            ])->toArray(),
        ];
    }
}