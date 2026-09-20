<?php
namespace App\Core;

use App\Http\Controllers\InstallerController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\SellerProfileController;
use App\Http\Controllers\AdminSellerController;
use App\Http\Controllers\SellerWorkController;
use App\Http\Controllers\AdminWorkController;
use App\Http\Controllers\AdminTaskController;
use App\Http\Controllers\SellerOfferController;

final class App
{
    public function __construct(private string $root) {}

    public function run(Request $request): void
    {
        try {
            $router = new Router();

            if (!Database::configured($this->root)) {
                $installer = new InstallerController($this->root);
                $router->get('/install', [$installer, 'show']);
                $router->post('/install', [$installer, 'install']);

                if ($request->path !== '/install') {
                    Response::redirect('/install');
                }

                $router->dispatch($request);
                return;
            }

            $db = Database::connection($this->root);
            $auth = new Auth($db);

            $csrf = function (Request $r): void {
                if ($r->method === 'POST' && !Csrf::verify((string) $r->input('_csrf'))) {
                    Response::abort(419, 'Sicherheitsprüfung fehlgeschlagen.');
                }
            };

            $sellerOnly = function () use ($auth): void {
                if (!$auth->seller()) {
                    Response::redirect('/login');
                }
            };

            $verifiedSeller = function () use ($auth): void {
                $seller = $auth->seller();
                if (!$seller) {
                    Response::redirect('/login');
                }
                if (empty($seller['email_verified_at'])) {
                    Response::redirect('/konto/email-bestaetigen');
                }
            };

            $adminOnly = function () use ($auth): void {
                if (!$auth->admin()) {
                    Response::redirect('/admin/login');
                }
            };

            $public = new PublicController($this->root, $db, $auth);
            $sellerAuth = new AuthController($this->root, $db, $auth);
            $seller = new SellerController($this->root, $db, $auth);
            $adminAuth = new AdminAuthController($this->root, $db, $auth);
            $admin = new AdminController($this->root, $db, $auth);
            $orders = new OrderController($this->root, $db, $auth);
            $adminOrders = new AdminOrderController($this->root, $db, $auth);
            $media = new MediaController($this->root, $db, $auth);
            $profile = new SellerProfileController($this->root, $db, $auth);
            $adminSellers = new AdminSellerController($this->root, $db, $auth);
            $sellerWork = new SellerWorkController($this->root, $db, $auth);
            $adminWork = new AdminWorkController($this->root, $db, $auth);
            $adminTasks = new AdminTaskController($this->root, $db);
            $sellerOffers = new SellerOfferController($db, $auth);

            $router->get('/media/evidence/{id}', [$media, 'evidence']);

            $router->get('/', [$public, 'home']);
            $router->get('/angebote', [$public, 'offers']);
            $router->get('/angebote/{id}', [$public, 'offer']);
            $router->get('/so-funktioniert-es', [$public, 'howItWorks']);
            $router->get('/faq', [$public, 'faq']);
            $router->get('/kontakt', [$public, 'contact']);
            $router->get('/impressum', [$public, 'imprint']);
            $router->get('/datenschutz', [$public, 'privacy']);
            $router->get('/bedingungen', [$public, 'terms']);

            $router->get('/registrieren', [$sellerAuth, 'registerForm']);
            $router->post('/registrieren', [$sellerAuth, 'register'], [$csrf]);
            $router->get('/login', [$sellerAuth, 'loginForm']);
            $router->post('/login', [$sellerAuth, 'login'], [$csrf]);
            $router->post('/logout', [$sellerAuth, 'logout'], [$csrf, $sellerOnly]);
            $router->get('/email-verifizieren/{token}', [$sellerAuth, 'verifyEmail']);
            $router->get('/konto/email-bestaetigen', [$sellerAuth, 'verificationNotice'], [$sellerOnly]);
            $router->post('/konto/email-bestaetigen', [$sellerAuth, 'resendVerification'], [$csrf, $sellerOnly]);
            $router->get('/passwort-vergessen', [$sellerAuth, 'forgotForm']);
            $router->post('/passwort-vergessen', [$sellerAuth, 'forgot'], [$csrf]);
            $router->get('/passwort-zuruecksetzen/{token}', [$sellerAuth, 'resetForm']);
            $router->post('/passwort-zuruecksetzen/{token}', [$sellerAuth, 'reset'], [$csrf]);

            $router->get('/konto', [$seller, 'dashboard'], [$sellerOnly]);
            $router->get('/konto/profil', [$profile, 'show'], [$sellerOnly]);
            $router->post('/konto/profil', [$profile, 'update'], [$csrf, $sellerOnly]);
            $router->post('/konto/profil/email', [$profile, 'changeEmail'], [$csrf, $sellerOnly]);
            $router->get('/konto/auftraege', [$seller, 'orders'], [$verifiedSeller]);
            $router->get('/konto/wallet', [$seller, 'wallet'], [$sellerOnly]);
            $router->get('/konto/benachrichtigungen', [$seller, 'notifications'], [$sellerOnly]);
            $router->post('/konto/angebote/{id}/annehmen', [$orders, 'acceptOffer'], [$csrf, $verifiedSeller]);
            $router->post('/konto/angebote/{id}/ablehnen', [$sellerOffers, 'decline'], [$csrf, $verifiedSeller]);
            $router->get('/konto/auftraege/{id}', [$orders, 'show'], [$verifiedSeller]);
            $router->post('/konto/auftraege/{id}/artikel', [$orders, 'saveItem'], [$csrf, $verifiedSeller]);
            $router->post('/konto/auftraege/{id}/nachweis', [$orders, 'uploadEvidence'], [$csrf, $verifiedSeller]);
            $router->post('/konto/auftraege/{id}/chat', [$orders, 'sendChat'], [$csrf, $verifiedSeller]);
            $router->post('/konto/auftraege/{id}/spontan', [$sellerWork, 'spontaneous'], [$csrf, $verifiedSeller]);
            $router->post('/konto/auftraege/{id}/aufgaben/{executionId}', [$sellerWork, 'submitTask'], [$csrf, $verifiedSeller]);
            $router->post('/konto/auftraege/{id}/beschaedigung', [$sellerWork, 'reportDamage'], [$csrf, $verifiedSeller]);
            $router->post('/konto/auftraege/{id}/beschaedigung/nachforderung/{requestId}', [$sellerWork, 'damageResponse'], [$csrf, $verifiedSeller]);

            $router->get('/admin/login', [$adminAuth, 'loginForm']);
            $router->post('/admin/login', [$adminAuth, 'login'], [$csrf]);
            $router->post('/admin/logout', [$adminAuth, 'logout'], [$csrf, $adminOnly]);
            $router->get('/admin', [$admin, 'dashboard'], [$adminOnly]);
            $router->get('/admin/verkaeuferinnen', [$adminSellers, 'index'], [$adminOnly]);
            $router->get('/admin/verkaeuferinnen/{id}', [$adminSellers, 'show'], [$adminOnly]);
            $router->post('/admin/verkaeuferinnen/{id}', [$adminSellers, 'update'], [$csrf, $adminOnly]);
            $router->post('/admin/verkaeuferinnen/{id}/loeschen', [$adminSellers, 'delete'], [$csrf, $adminOnly]);
            $router->get('/admin/aufgaben', [$adminTasks, 'index'], [$adminOnly]);
            $router->post('/admin/aufgaben', [$adminTasks, 'save'], [$csrf, $adminOnly]);
            $router->get('/admin/kategorien', [$admin, 'categories'], [$adminOnly]);
            $router->post('/admin/kategorien', [$admin, 'createCategory'], [$csrf, $adminOnly]);
            $router->post('/admin/kategorien/{id}', [$admin, 'updateCategory'], [$csrf, $adminOnly]);
            $router->post('/admin/kategorien/{id}/duplizieren', [$admin, 'duplicateCategory'], [$csrf, $adminOnly]);
            $router->post('/admin/kategorien/{id}/loeschen', [$admin, 'deleteCategory'], [$csrf, $adminOnly]);
            $router->get('/admin/angebote', [$admin, 'offers'], [$adminOnly]);
            $router->get('/admin/angebote/neu', [$admin, 'offerCreateForm'], [$adminOnly]);
            $router->post('/admin/angebote/neu', [$admin, 'createOffer'], [$csrf, $adminOnly]);
            $router->get('/admin/angebote/{id}/bearbeiten', [$admin, 'offerEditForm'], [$adminOnly]);
            $router->post('/admin/angebote/{id}/bearbeiten', [$admin, 'updateOffer'], [$csrf, $adminOnly]);
            $router->get('/admin/auftraege/{id}', [$adminOrders, 'show'], [$adminOnly]);
            $router->post('/admin/auftraege/{id}/vorab-freigeben', [$adminOrders, 'approvePrecheck'], [$csrf, $adminOnly]);
            $router->post('/admin/auftraege/{id}/nachweise/{evidenceId}/pruefen', [$adminOrders, 'reviewEvidence'], [$csrf, $adminOnly]);
            $router->post('/admin/auftraege/{id}/verstoesse/{violationId}', [$adminOrders, 'decideViolation'], [$csrf, $adminOnly]);
            $router->post('/admin/auftraege/{id}/zusatztage', [$adminOrders, 'addManualDay'], [$csrf, $adminOnly]);
            $router->post('/admin/auftraege/{id}/chat', [$adminOrders, 'sendChat'], [$csrf, $adminOnly]);
            $router->post('/admin/auftraege/{id}/spontan', [$adminWork, 'spontaneous'], [$csrf, $adminOnly]);
            $router->post('/admin/auftraege/{id}/aufgabe', [$adminWork, 'addTask'], [$csrf, $adminOnly]);
            $router->post('/admin/auftraege/{id}/aufgaben/{executionId}/pruefen', [$adminWork, 'reviewTask'], [$csrf, $adminOnly]);
            $router->post('/admin/auftraege/{id}/beschaedigung/{caseId}/nachfordern', [$adminWork, 'requestDamageEvidence'], [$csrf, $adminOnly]);
            $router->post('/admin/auftraege/{id}/beschaedigung/{caseId}/entscheiden', [$adminWork, 'decideDamage'], [$csrf, $adminOnly]);

            $router->dispatch($request);
        } catch (\Throwable $e) {
            Logger::error($this->root, $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            if (defined('APP_DEBUG') && APP_DEBUG) {
                throw $e;
            }

            Response::abort(500, 'Ein interner Fehler ist aufgetreten.');
        }
    }
}
