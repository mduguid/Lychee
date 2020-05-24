<?php

/** @noinspection PhpUndefinedClassInspection */

namespace App\ModelFunctions;

use App;
use App\Configs;
use App\Exceptions\NotLoggedInException;
use App\Exceptions\RequestAdminDataException;
use App\Exceptions\UserNotFoundException;
use App\Logs;
use App\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class SessionFunctions
{
	private $user_data = null;

	public function log_as_id($id)
	{
		if (App::runningUnitTests()) {
			Session::put('login', true);
			Session::put('UserID', $id);
		}
	}

	/**
	 * Return true if the user is logged in (Admin or User)
	 * Return false if it is Guest access.
	 *
	 * @return bool
	 */
	public function is_logged_in()
	{
		if (Session::get('login') === true) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Return true if the user is logged in and an admin.
	 *
	 * @return bool
	 */
	public function is_admin()
	{
		return Session::get('login') && Session::get('UserID') === 0;
	}

	public function can_upload(): bool
	{
		return $this->id() == 0 || $this->getUserData()->upload;
	}

	/**
	 * Return the current ID of the user
	 * what happens when UserID is not set? :p.
	 *
	 * @return int
	 */
	public function id()
	{
		if (!Session::get('login')) {
			throw new NotLoggedInException();
		}

		return Session::get('UserID');
	}

	/**
	 * Return User object.
	 */
	public function getUserData(): User
	{
		if ($this->user_data != null) {
			return $this->user_data;
		}

		$id = $this->id();
		if ($id > 0) {
			$this->user_data = User::find($this->id);

			if ($this->user_data == null) {
				Logs::error(__METHOD__, __LINE__, 'Could not find specified user (' . $id . ')');
				throw new UserNotFoundException($id);
			}

			return $this->user_data;
		}

		Logs::error(__METHOD__, __LINE__, 'Trying to get a User from Admin ID.');
		throw new RequestAdminDataException();
	}

	/**
	 * Return true if the currently logged in user is the one provided
	 * (or if that user is Admin).
	 *
	 * @param int userId
	 *
	 * @return bool
	 */
	public function is_current_user(int $userId)
	{
		return Session::get('login') && (Session::get('UserID') === $userId || Session::get('UserID') === 0);
	}

	/**
	 * Sets the session values when no there is no username and password in the database.
	 *
	 * @return bool returns true when no login was found
	 */
	public function noLogin()
	{
		$configs = Configs::get();

		// Check if login credentials exist and login if they don't
		if (
			isset($configs['username']) && $configs['username'] === '' &&
			isset($configs['password']) && $configs['password'] === ''
		) {
			Session::put('login', true);
			Session::put('UserID', 0);

			return true;
		}

		return false;
	}

	/**
	 * Given a username, password and ip (for logging), try to log the user.
	 * returns true if succeed
	 * returns false if fail.
	 *
	 * @param string $username
	 * @param string $password
	 * @param string $ip
	 *
	 * @return bool
	 */
	public function log_as_user(string $username, string $password, string $ip)
	{
		$user = User::where('username', '=', $username)->first();

		if ($user != null && Hash::check($password, $user->password)) {
			Session::put('login', true);
			Session::put('UserID', $user->id);
			Logs::notice(__METHOD__, __LINE__, 'User (' . $username . ') has logged in from ' . $ip);
			$this->user_data = $user;

			return true;
		}

		return false;
	}

	/**
	 * Given a username, password and ip (for logging), try to log the user as admin.
	 * returns true if succeed
	 * returns false if fail.
	 *
	 * @param string $username
	 * @param string $password
	 * @param string $ip
	 *
	 * @return bool
	 */
	public function log_as_admin(string $username, string $password, string $ip)
	{
		$configs = Configs::get();

		if (Hash::check($username, $configs['username']) && Hash::check($password, $configs['password'])) {
			Session::put('login', true);
			Session::put('UserID', 0);
			Logs::notice(__METHOD__, __LINE__, 'User (' . $username . ') has logged in from ' . $ip);

			return true;
		}

		return false;
	}

	/**
	 * Given an albumID, check if it exists in the visible_albums session variable.
	 *
	 * @param $albumID
	 *
	 * @return bool
	 */
	public function has_visible_album($albumID)
	{
		if (!Session::has('visible_albums')) {
			return false;
		}

		$visible_albums = Session::get('visible_albums');
		$visible_albums = explode('|', $visible_albums);

		return in_array($albumID, $visible_albums);
	}

	/**
	 * Add new album to the visible_albums session variable.
	 *
	 * @param $albumID
	 */
	public function add_visible_album($albumID)
	{
		if (Session::has('visible_albums')) {
			$visible_albums = Session::get('visible_albums');
		} else {
			$visible_albums = '';
		}

		$visible_albums = explode('|', $visible_albums);
		if (!in_array($albumID, $visible_albums)) {
			$visible_albums[] = $albumID;
		}

		$visible_albums = implode('|', $visible_albums);
		Session::put('visible_albums', $visible_albums);
	}

	/**
	 * Log out the current user.
	 */
	public function logout()
	{
		$this->user_data = null;
		Session::flush();
	}
}
