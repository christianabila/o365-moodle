<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
namespace local_o365\feature\usersync;

use local_o365\oauth2\token;
use local_o365\tests\mockhttpclient;

/**
 * Unit tests for the class main_test
 *
 * @package   local_o365
 * @copyright 2025 eDaktik GmbH {@link https://www.edaktik.at/}
 * @author    Christian Abila <christian.abila@edaktik.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_o365\feature\usersync\main
 */
final class main_test extends \advanced_testcase {
    /**
     * Get a mock token object to use when constructing the API client.
     *
     * @return token The mock token object.
     */
    protected function get_mock_clientdata() {
        $oidcconfig = (object) [
            'clientid' => 'clientid',
            'clientsecret' => 'clientsecret',
            'authendpoint' => 'http://example.com/auth',
            'tokenendpoint' => 'http://example.com/token',
        ];

        $clientdata = new \local_o365\oauth2\clientdata($oidcconfig->clientid, $oidcconfig->clientsecret,
            $oidcconfig->authendpoint, $oidcconfig->tokenendpoint);

        return $clientdata;
    }

    /**
     * Get a mock token object to use when constructing the API client.
     *
     * @return token The mock token object.
     */
    protected function get_mock_token() {
        $httpclient = new mockhttpclient();

        $tokenrec = (object) [
            'token' => 'token',
            'expiry' => time() + 1000,
            'refreshtoken' => 'refreshtoken',
            'scope' => 'scope',
            'user_id' => '2',
            'tokenresource' => 'resource',
        ];

        $clientdata = $this->get_mock_clientdata();
        $token = new token($tokenrec->token, $tokenrec->expiry, $tokenrec->refreshtoken,
            $tokenrec->scope, $tokenrec->tokenresource, $tokenrec->user_id, $clientdata, $httpclient);

        return $token;
    }

    /**
     * Get sample Microsoft Entra ID userdata.
     *
     * @param int $i A counter to generate unique data.
     * @return array Array of Microsoft Entra ID user data.
     */
    protected function get_entra_id_userinfo($i = 0) {
        return [
            'odata.type' => 'Microsoft.WindowsAzure.ActiveDirectory.User',
            'objectType' => 'User',
            'objectId' => '00000000-0000-0000-0000-00000000000' . $i,
            'id' => '00000000-0000-0000-0000-00000000000' . $i,
            'city' => 'Toronto',
            'country' => ($i == 3) ? 'Canada' : 'CA',
            'department' => 'Dev',
            'givenName' => 'Test',
            'mail' => 'testuser' . $i . '@example.onmicrosoft.com',
            'surname' => 'User' . $i,
            'preferredLanguage' => ($i == 3) ? 'sa-IN' : 'en-US',
            'userPrincipalName' => 'testuser' . $i . '@example.onmicrosoft.com',
            'deleted' => $i % 2,
        ];
    }

    public function test_suspend_users(): void {

    }

    public function test_delete_users(): void {

    }
}
