<?php
/**
 * Copyright Maxim Bykovskiy © 2017.
 */

/**
 * Created by PhpStorm.
 * User: sherh
 * Date: 17.11.2017
 * Time: 13:50
 */

namespace VkLib;

class Vk
{
    private $url = "https://api.vk.com/method/";
    private $tokens = [];
    private $serviceToken = '';
    private $v = '';

    function __construct(
        array $tokens,
        $serviceToken = '22f7a84b22f7a84b22f7a84b5c22a95c8d222f722f7a84b7b128a91a7f107dc6d9f4d6b',
        $v = '5.80'
    ) {
        $this->tokens = $tokens;
        $this->serviceToken = $serviceToken;
        $this->v = $v;
    }

    private function postCurl($url, $files)
    {
        $boundary = '---------------------' . substr(md5(rand(0, 32000)), 0, 10);
        $postData = '';
        $postData .= '--' . $boundary . "\n";
        $postData .= 'Content-Disposition: form-data; name="photo"; filename="' . basename($files) . '"' . "\n";
        $postData .= 'Content-Type: multipart/form-data' . "\n";
        $postData .= 'Content-Transfer-Encoding: binary' . "\n\n";
        $postData .= file_get_contents($files) . "\n";
        $postData .= '--' . $boundary . "\n";
        $params = array
        (
            'http' => array
            (
                'method' => 'POST',
                'content' => $postData,
                'header' => array
                (
                    'Content-Type: multipart/form-data; boundary=' . $boundary
                )
            )
        );
        $responce = file_get_contents(
            $url,
            false,
            stream_context_create($params)
        );
        return json_decode($responce);
    }

    private function getCurl($link, $json = true)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $link);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_COOKIEFILE, dirname(__FILE__) . '/vk.txt'); #PHP>5.3.6 dirname(__FILE__) -> __DIR__
        curl_setopt($curl, CURLOPT_COOKIEJAR, dirname(__FILE__) . '/vk.txt'); #PHP>5.3.6 dirname(__FILE__) -> __DIR__
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        try {
            if ($json == true) {
                $out = json_decode(curl_exec($curl));
                return $out;
            } else {
                $out = curl_exec($curl);
                return $out;
            }
        } catch (Exception $e) {
            $this->status = 'Ошибка соединения';
        }
    }

    public function makeRequest($method, $data)
    {
        $link = $this->url . $method . '?' . http_build_query($data);
        $result = $this->getCurl($link);
        return $result;
    }

    public function getUserInfo($uid)
    {
        $params = array(
            'access_token' => $this->serviceToken,
            'user_ids' => $uid,
            'fields' => 'verified,sex,personal,bdate,nickname,city,country,home_town,contacts',
            'name_case' => 'Nom',
            'v' => $this->v,
            'lang' => 'ru'
        );
        $result = $this->makeRequest('users.get', $params);
        return $result;
    }

    public function getMessages($time, $stime, $mass = [])
    {
        foreach ($this->tokens as $token) {
            $params = array(
                'access_token' => $token,
                'count' => 100,
                'v' => $this->v,
                'lang' => 'ru'
            );
            $dialog = $this->makeRequest('messages.getConversations', $params);
            sleep(1);

            foreach ($dialog->response->items as $mas) {
                $date = $mas->last_message->date;
                if ($mas->conversation->peer->type == 'user' && $date >= $time && $date <= $stime ) {


                    $uid = $mas->conversation->peer->id;
                    $params = array(
                        'access_token' => $token,
                        'count' => 100,
                        'peer_id' => $uid,
                        'rev' => 1,
                        'v' => $this->v,
                        'lang' => 'ru'
                    );
                    $message = $this->makeRequest('messages.getHistory', $params);
                    sleep(1);

                    foreach ($message->response->items as $mess) {
                        if ($mess->date >= $time && !empty($mess->text)) {
                            if ($mess->random_id != 0) {
                                $txt = 'Вы(' . date('Y-m-d H:i:s', $mess->date) . '): ' . $mess->text . '; ';
                            } else {
                                $txt = 'Клиент(' . date('Y-m-d H:i:s', $mess->date) . '): ' . $mess->text . '; ';
                            }
                            $mass[$uid][] = $txt;
                        }
                    }
                }

            }
        }
        return $mass;
    }

    public function getLastMessages($count, $uid, $mass = [])
    {
        $params = array(
            'access_token' => $this->tokens[0],
            'count' => $count,
            'peer_id' => $uid,
            'rev' => 1,
            'v' => $this->v,
            'lang' => 'ru'
        );

        $message = $this->makeRequest('messages.getHistory', $params);

        foreach ($message->response->items as $mess) {
            if (!empty($mess->text)) {
                if (strstr($mess->text, 'Номер заказа - ')) {
                    $mass[] = $mess->text;
                    continue;
                }
                if ($mess->random_id != 0) {
                    $txt = 'Вы(' . date('Y-m-d H:i:s', $mess->date) . '): ' . $mess->text . '; ';
                } else {
                    $txt = 'Клиент(' . date('Y-m-d H:i:s', $mess->date) . '): ' . $mess->text . '; ';
                }
                $mass[] = $txt;
            }
        }
        return $mass;
    }

    public function getGroupComments(array $ids, $time, $mass = [])
    {
        foreach ($ids as $id) {
            $params = array(
                'access_token' => $this->serviceToken,
                'count' => 100,
                'owner_id' => $id,
                'extended' => 1,
                'v' => $this->v
            );
            $wall = $this->makeRequest('wall.get', $params);
            //echo "<pre>"; print_r($wall); echo "</pre>";
            foreach ($wall->response->items as $mas) {
                if ($mas->comments->count > 0) {
                    $params = array(
                        'access_token' => $this->serviceToken,
                        'count' => 100,
                        'owner_id' => $id,
                        'post_id' => $mas->id,
                        'sort' => 'desc',
                        'v' => $this->v
                    );
                    $comment = $this->makeRequest('wall.getComments', $params);
                    foreach ($comment->response->items as $com) {
                        if ($com->date >= $time && !empty($com->text)) {
                            $uid = $com->from_id;
                            $txt = 'Клиент(' . date('Y-m-d H:i:s',
                                    $com->date) . ') в группе "' . $wall->response->groups[0]->name . '": ' . $com->text . '; ';
                            $mass[$uid][] = $txt;
                        }
                    }
                }
            }
        }
        return $mass;
    }

    public function sendMessage($num, $userid, $message)
    {
        $params = array(
            'access_token' => $this->tokens[$num],
            'user_id' => $userid,
            'message' => $message,
            'v' => $this->v
        );
        $result = $this->makeRequest('messages.send', $params);
        return $result;
    }

    public function banUser($num, $userid)
    {
        $params = array(
            'access_token' => $this->tokens[$num],
            'user_id' => $userid,
            'v' => $this->v
        );
        $result = $this->makeRequest('account.banUser', $params);
        return $result;
    }

    public function unBanUser($num, $userid)
    {
        $params = array(
            'access_token' => $this->tokens[$num],
            'user_id' => $userid,
            'v' => $this->v
        );
        $result = $this->makeRequest('account.unbanUser', $params);
        return $result;
    }

    public function getBanned($num)
    {
        $params = array(
            'access_token' => $this->tokens[$num],
            'count' => 200,
            'v' => $this->v
        );
        $result = $this->makeRequest('account.getBanned', $params);
        return $result;
    }

    public function addFriend($num, $userid)
    {
        $params = array(
            'access_token' => $this->tokens[$num],
            'user_id' => $userid,
            'follow' => 0,
            'v' => $this->v
        );
        $result = $this->makeRequest('friends.add', $params);
        return $result;
    }

    public function deleteFriend($num, $userid)
    {
        $params = array(
            'access_token' => $this->tokens[$num],
            'user_id' => $userid,
            'v' => $this->v
        );
        $result = $this->makeRequest('friends.delete', $params);
        return $result;
    }

    public function inviteGroup($num, $userid, $groupid)
    {
        $params = array(
            'access_token' => $this->tokens[$num],
            'user_id' => $userid,
            'group_id' => $groupid,
            'v' => $this->v
        );
        $result = $this->makeRequest('groups.invite', $params);
        return $result;
    }

    public function addBoard($num, $groupid, $title, $text)
    {
        $params = array(
            'access_token' => $this->tokens[$num],
            'title' => $title,
            'text' => $text,
            'from_group' => 1,
            'group_id' => $groupid,
            'v' => $this->v
        );
        $result = $this->makeRequest('board.addTopic', $params);
        return $result;
    }

    public function closeBoard($num, $groupid, $topicid)
    {
        $params = array(
            'access_token' => $this->tokens[$num],
            'topic_id' => $topicid,
            'group_id' => $groupid,
            'v' => $this->v
        );
        $result = $this->makeRequest('board.closeTopic', $params);
        return $result;
    }

    public function writeBoard($num, $groupid, $topic_id, $text)
    {
        $params = array(
            'access_token' => $this->tokens[$num],
            'from_group' => 1,
            'message' => $text,
            'topic_id' => $topic_id,
            'group_id' => $groupid,
            'v' => $this->v
        );
        $result = $this->makeRequest('board.createComment', $params);
        return $result;
    }

    public function writeComment($num, $owner_id, $post_id, $text, $reply_to_comment = 0)
    {
        $params = array(
            'access_token' => $this->tokens[$num],
            'from_group' => 1,
            'message' => $text,
            'post_id' => $post_id,
            'owner_id' => $owner_id,
            'v' => $this->v
        );
        if ($reply_to_comment != 0) {
            $params['reply_to_comment'] = $reply_to_comment;
        }
        $result = $this->makeRequest('wall.createComment', $params);
        return $result;
    }

    public function getBoard(array $ids, $time, $mass = [])
    {
        foreach ($ids as $id) {
            $params = array(
                'access_token' => $this->serviceToken,
                'count' => 100,
                'group_id' => $id,
                'order' => 1,
                'v' => $this->v
            );
            $wall = $this->makeRequest('board.getTopics', $params);
            //echo "<pre>"; print_r($wall); echo "</pre>";
            foreach ($wall->response->items as $mas) {
                if ($mas->comments > 0 && $mas->updated >= $time) {
                    $title = $mas->title;
                    $params = array(
                        'access_token' => $this->serviceToken,
                        'count' => 100,
                        'group_id' => $id,
                        'topic_id' => $mas->id,
                        'sort' => 'desc',
                        'v' => $this->v
                    );
                    $comment = $this->makeRequest('board.getComments', $params);
                    foreach ($comment->response->items as $com) {
                        if ($com->date >= $time && !empty($com->text)) {
                            $uid = $com->from_id;
                            $txt = 'Клиент(' . date('Y-m-d H:i:s',
                                    $com->date) . ') в обсуждении "' . $title . '": ' . $com->text . '; ';
                            $mass[$uid][] = $txt;
                        }
                    }
                }
            }
        }
        return $mass;
    }

    public function sendPhoto($num, $userid, $photo)
    {
        $params = array(
            'access_token' => $this->tokens[$num],
            'peer_id' => $userid,
            'v' => $this->v
        );
        $result = $this->makeRequest('photos.getMessagesUploadServer', $params);
        $url = $result->response->upload_url;
        $lin = explode(".", $photo);
        $name = md5(time() . rand(100000, 999999)) . '.' . $lin[count($lin) - 1];
        file_put_contents($name, file_get_contents($photo));
        $result = $this->postCurl($url, $name);
        $result->access_token = $this->tokens[$num];
        $result = $this->makeRequest('photos.saveMessagesPhoto', $result);
        $params = array(
            'access_token' => $this->tokens[$num],
            'user_id' => $userid,
            'attachment' => $result->response[0]->id,
            'v' => $this->v
        );
        $result = $this->makeRequest('messages.send', $params);
        unlink($name);
        return $result;
    }
}