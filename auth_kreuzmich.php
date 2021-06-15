<?php
/**
*
* kreuzmich auth plug-in for phpBB3
*
* Authentication plug-ins is largely down to Sergey Kanareykin, our thanks to him.
*
* @package login
* @version v0.0.6
* @copyright (c) 2005 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
    exit;
}

/**
* Connect to kreuzmich server
* Only allow changing authentication to kreuzmich if we can connect to the server
* Called in acp_board while setting authentication plugins
*/
function init_kreuzmich()
{
    global $config, $user;

    return false;
}

/**
* Login function
*/
function login_kreuzmich(&$username, &$password)
{
    global $db, $config, $user;

    // do not allow empty password
    if (!$password)
    {
        return array(
            'status'    => LOGIN_ERROR_PASSWORD,
            'error_msg' => 'NO_PASSWORD_SUPPLIED',
            'user_row'  => array('user_id' => ANONYMOUS),
        );
    }

    if (!$username)
    {
        return array(
            'status'    => LOGIN_ERROR_USERNAME,
            'error_msg' => 'LOGIN_ERROR_USERNAME',
            'user_row'  => array('user_id' => ANONYMOUS),
        );
    }

	 
    $username_clean = utf8_clean_string($username);

    if (
      preg_match('/[ß]/', $username) == 1 ||
      preg_match('/[äÄöÖüÜ]/', $username_clean) == 1
    )
    {
      return array(
        'status'    => LOGIN_ERROR_USERNAME_UMLAUT,
        'error_msg' => 'LOGIN_ERROR_USERNAME_UMLAUT',
        'user_row'  => array('user_id' => ANONYMOUS),
      );
    }
	
    $auth_url = "https://".$config['kreuzmich_auth_url'];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $auth_url);
    
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, array('username' => $username, 'password' => $password));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Set your login and password for authentication
    curl_setopt($ch, CURLOPT_USERPWD, $config['kreuzmich_auth_user'].":".$config['kreuzmich_auth_password']);

    $jsondata = @curl_exec($ch);
	curl_close($ch);
    $jsonobj = @json_decode($jsondata);

    if ( !isset($jsonobj->success) )
    {
        return array(
            'status'    => LOGIN_ERROR_EXTERNAL_AUTH,
            'error_msg' => 'KREUZMICH_AUTH_NO_SERVER_CONNECTION',
            'user_row'  => array('user_id' => ANONYMOUS),
        );
    } 

	// deny auth of expired users
	// check ACP settings & existing JSON attribute 'expired' then compare to current time
	if ( (!$config[kreuzmich_expired_users])  && isset($jsonobj->user->expired) && ($jsonobj->user->expired) )
	{
			$jsonobj->success = 0;
			$jsonobj->reason = "Expired";
	} 
	
    if ($jsonobj->success)
    {
       $sql ='SELECT user_id, username, user_password, user_passchg, user_email, user_type
              FROM ' . USERS_TABLE . "
              WHERE username_clean = '" . $db->sql_escape($username_clean) . "'";
       $result = $db->sql_query($sql);
       $row = $db->sql_fetchrow($result);
       $db->sql_freeresult($result);

        if ($row)
        {
            unset($jsonobj);
			

            // User inactive...
            if ($row['user_type'] == USER_INACTIVE || $row['user_type'] == USER_IGNORE)
            {
                return array(
                    'status'    => LOGIN_ERROR_ACTIVE,
                    'error_msg' => 'ACTIVE_ERROR',
                    'user_row'  => $row,
                );
            }

            // Successful login... set user_login_attempts to zero...
            return array(
                'status'    => LOGIN_SUCCESS,
                'error_msg' => false,
                'user_row'  => $row,
            );
        }
        else
        {
            // retrieve default group id
            $sql = 'SELECT group_id
                FROM ' . GROUPS_TABLE . "
                WHERE group_name = '" . $db->sql_escape('REGISTERED') . "'
                    AND group_type = " . GROUP_SPECIAL;
            $result = $db->sql_query($sql);
            $row = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);

            if (!$row)
            {
                trigger_error('NO_GROUP');
            }

            // generate user account data
            $kreuzmich_auth_user_row = array(
                'username'      => $username,
                'user_password' => phpbb_hash($password),
                'user_email'    => $jsonobj->user->email,
                'group_id'      => (int) $row['group_id'],
                'user_type'     => USER_NORMAL,
                'user_ip'       => $user->ip,
            );

            unset($jsonobj);

            // this is the user's first login so create an empty profile
            return array(
                'status'    => LOGIN_SUCCESS_CREATE_PROFILE,
                'error_msg' => false,
                'user_row'  => $kreuzmich_auth_user_row,
            );
        }
    }
    else
    {
        return array(
            'status'    => LOGIN_ERROR_EXTERNAL_AUTH,
            'error_msg' => 'KREUZMICH_AUTH_INVALID_DATA',
            'user_row'  => array('user_id' => ANONYMOUS),
        );
    }

}

/**
* This function is used to output any required fields in the authentication
* admin panel. It also defines any required configuration table fields.
*/
function acp_kreuzmich(&$new)
{
    global $user;

    $tpl = '

    <dl>
        <dt><label for="kreuzmich_auth_user">' . $user->lang['KREUZMICH_AUTH_USER'] . ':</label><br /><span>' . $user->lang['KREUZMICH_AUTH_USER_EXPLAIN'] . '</span></dt>
        <dd><input type="text" id="kreuzmich_auth_user" size="40" name="config[kreuzmich_auth_user]" value="' . $new['kreuzmich_auth_user'] . '" /></dd>
    </dl>
    <dl>
        <dt><label for="kreuzmich_auth_password">' . $user->lang['KREUZMICH_AUTH_PASSWORD'] . ':</label><br /><span>' . $user->lang['KREUZMICH_AUTH_PASSWORD_EXPLAIN'] . '</span></dt>
        <dd><input type="password" id="kreuzmich_auth_password" size="40" name="config[kreuzmich_auth_password]" value="' . $new['kreuzmich_auth_password'] . '" /></dd>
    </dl>
    <dl>
        <dt><label for="kreuzmich_auth_url">' . $user->lang['KREUZMICH_AUTH_URL'] . ':</label><br /><span>' . $user->lang['KREUZMICH_AUTH_URL_EXPLAIN'] . '</span></dt>
        <dd><input type="text" id="kreuzmich_auth_url" size="40" name="config[kreuzmich_auth_url]" value="' . $new['kreuzmich_auth_url'] . '" /></dd>
    </dl>
	<dl>
        <dt><label>' . $user->lang['KREUZMICH_EXPIRED_USERS'] . ':</label><br /><span>' . $user->lang['KREUZMICH_EXPIRED_USERS_EXPLAIN'] . '</span></dt>
        <dd><input type="text" id="kreuzmich_expired_users" size="40"  name="config[kreuzmich_expired_users]" value="' . $new['kreuzmich_expired_users'] . '" /></dd>
	</dl>	
    ';

    // These are fields required in the config table
    return array(
        'tpl'        => $tpl,
        'config'    => array('kreuzmich_auth_user', 'kreuzmich_auth_password', 'kreuzmich_auth_url', 'kreuzmich_expired_users')
    );
}

?>
