<?php
/**
 * Passwordless sign-in is a product decision, not a visual detail: if
 * wp-login.php is left open, there is a second way in that the sign-in screen
 * does not show. These tests pin down what is closed and what is not.
 */

namespace Tests\Unit\DiluxOneUsers;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

class PasswordlessTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		// The module registers hooks as it loads, so it is loaded with Brain
		// Monkey active. The hooks are not stubbed in wordpress-stubs.php
		// precisely so as not to cover its own.
		Monkey\setUp();
		require_once DILUXONE_USERS_DIR . 'includes/options.php';
		require_once DILUXONE_USERS_DIR . 'includes/passwordless.php';
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @dataProvider actionsThatAreClosed
	 */
	public function test_password_actions_are_redirected(string $action): void {
		$this->assertTrue(
			diluxone_users_should_redirect($action),
			"La acción '{$action}' tendría que ir a la página de acceso"
		);
	}

	public static function actionsThatAreClosed(): array {
		return [
			'login por defecto'   => [''],
			'login explícito'     => ['login'],
			'registro nativo'     => ['register'],
			'olvidé mi clave'     => ['lostpassword'],
			'acción desconocida'  => ['algo-que-no-existe'],
		];
	}

	/**
	 * @dataProvider actionsThatStayOpen
	 */
	public function test_flows_that_are_not_login_keep_working(string $action): void {
		$this->assertFalse(
			diluxone_users_should_redirect($action),
			"La acción '{$action}' no puede redirigirse: rompe un flujo que WordPress necesita"
		);
	}

	public static function actionsThatStayOpen(): array {
		return [
			'cerrar sesión'            => ['logout'],
			'contraseña de entrada'    => ['postpass'],
			'reseteo iniciado por admin' => ['rp'],
			'reseteo, segundo paso'    => ['resetpass'],
			'confirmación RGPD'        => ['confirmaction'],
		];
	}

	public function test_the_escape_hatch_shows_the_native_form(): void {
		$this->assertFalse(
			diluxone_users_should_redirect('login', ['diluxone-users-admin' => '1']),
			'Sin esta salida, un fallo del correo o del proveedor social deja a todos afuera del sitio'
		);
	}

	public function test_the_dashboard_interstitial_login_is_not_redirected(): void {
		// It is the modal appearing inside wp-admin when the session expires:
		// redirecting it would break the screen that opened it.
		$this->assertFalse(
			diluxone_users_should_redirect('login', ['interim-login' => '1'])
		);
	}

	public function test_the_escape_hatch_does_not_reopen_native_registration(): void {
		// diluxone-users-admin exists so an administrator can get in with their
		// password, not to re-enable sign-ups through wp-login.php.
		// This test fails if somebody moves the `register` check ahead of the
		// `diluxone-users-admin` one.
		$this->assertFalse(
			diluxone_users_should_redirect('register', ['diluxone-users-admin' => '1']),
			'El flujo de emergencia muestra el formulario nativo; el registro nativo está apagado por option_users_can_register'
		);
	}
}
