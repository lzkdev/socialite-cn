<?php
namespace Liaol\SocialiteCn\Providers;

use Liaol\SocialiteCn\Providers\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Liaol\SocialiteCn\User;


class QQProvider extends AbstractProvider implements ProviderInterface
{
    protected $openId;

     /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://graph.qq.com/oauth2.0/authorize', $state);
    }


    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return 'https://graph.qq.com/oauth2.0/token';
    }

    /** * Get the access token for the given code.
     *
     * @param  string  $code
     * @return string
     */
    public function getAccessToken($code)
    {
        $query=$this->getTokenFields($code);
        $response = $this->getHttpClient()->get($this->getTokenUrl(),['query'=>($this->getTokenFields($code))]);
        return  $this->parseAccessToken($this->removeCallback($response->getBody()));
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenFields($code)
    {
        return [
            'client_id' => $this->clientId, 'client_secret' => $this->clientSecret,
            'code' => $code, 'redirect_uri' => $this->redirectUrl,
            'grant_type'=>'authorization_code'
        ];
    }


    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token) { 
        $openId = $this->getOpenId($token);
        $response = $this->getHttpClient()->get('https://graph.qq.com/user/get_user_info',['query'=>[
            'access_token'=>$token,
            'openid'=>$openId,
            // this should be clientId not client_id,
            'oauth_consumer_key'=>$this->clientId,
        ]]);
        $data = json_decode($response->getBody(), true);
        if ($data['ret']==0) {
            return $data;
        }else{
            throw new ErrorCodeException($data['ret'],$data['msg']);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        return (new User)->setRaw($user)->map([
            'id' => $this->openId, 'nickname' => $user['nickname'],'name' => $user['nickname'], 'avatar' => $user['figureurl_qq_2'],
        ]);
    }

    /**
        * @Synopsis  get openid
        *
        * @Param $token
        *
        * @Returns  uid string 
     */
    protected function getOpenId($token)
    {
        $response = $this->getHttpClient()->get('https://graph.qq.com/oauth2.0/me',['query'=>['access_token'=>$token]]);
        $this->openId =  $this->checkError(json_decode($this->removeCallback($response->getBody()), true))['openid'];
        return $this->openId;
    }

    /**
        * @Synopsis  check http error 
        *
        * @Param $data
        *
        * @Returns  mix 
     */
    protected function checkError($data)
    {
        if (isset($data['error'])) {
            throw new ErrorCodeException($data['error'],$data['error_description']);
        }
        return $data;
    }

    protected function parseAccessToken($body)
    {
        $result=array();
        if (!$this->checkError(json_decode($body,true))) {
            $body_array = explode('&', $body);
            foreach ($body_array as $item) {
                $split = explode('=', $item);
                $result[$split[0]] = $split[1];
            }
        }
        return $result;
    }

    protected function removeCallback($body)
    {
         return  str_replace(['callback(',')',';'],'',$body);
    }
}
