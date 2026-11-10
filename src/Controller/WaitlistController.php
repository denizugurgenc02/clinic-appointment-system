<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Session\Session;
use App\Core\View\View;
use App\Service\AuthService;
use App\Service\WaitlistService;
use DomainException;

/**
 * Hastanın bekleme listesi işlemleri: sıraya gir, teklifi kabul/ret, sıradan çık.
 */
final class WaitlistController extends AbstractController
{
    public function __construct(
        View $view,
        Session $session,
        AuthService $auth,
        private readonly WaitlistService $waitlist,
    ) {
        parent::__construct($view, $session, $auth);
    }

    public function join(Request $request): Response
    {
        try {
            $desired = $request->input('desired_date');
            $this->waitlist->join(
                $this->user()->id(),
                self::requireInt($request->input('doctor_id'), 'doctor_id'),
                $desired === null || $desired === '' ? null : self::requireDate($desired, 'Y-m-d', 'desired_date'),
            );
        } catch (DomainException $e) {
            return $this->failed($request, $e, '/appointments/book');
        }

        $this->success('Bekleme listesine eklendiniz. Bir randevu iptal edildiğinde size 30 dakikalık teklif gelecek.');

        return $this->redirect('/appointments');
    }

    public function accept(Request $request): Response
    {
        try {
            $appointment = $this->waitlist->accept(self::requireInt($request->param('id'), 'id'), $this->user());
        } catch (DomainException $e) {
            return $this->failed($request, $e, '/appointments');
        }

        $this->success('Teklifi kabul ettiniz. Randevunuz: ' . $appointment->startsAt->format('d.m.Y H:i'));

        return $this->redirect('/appointments');
    }

    public function decline(Request $request): Response
    {
        try {
            $this->waitlist->decline(self::requireInt($request->param('id'), 'id'), $this->user());
        } catch (DomainException $e) {
            return $this->failed($request, $e, '/appointments');
        }

        $this->success('Teklifi reddettiniz; slot sıradaki hastaya teklif edildi.');

        return $this->redirect('/appointments');
    }

    public function leave(Request $request): Response
    {
        try {
            $this->waitlist->leave(self::requireInt($request->param('id'), 'id'), $this->user());
        } catch (DomainException $e) {
            return $this->failed($request, $e, '/appointments');
        }

        $this->success('Bekleme listesinden çıktınız.');

        return $this->redirect('/appointments');
    }
}
