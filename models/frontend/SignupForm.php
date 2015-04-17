<?php
namespace infoweb\user\models\frontend;

use Yii;
use yii\base\Model;
use infoweb\user\models\Profile;

/**
 * Signup form
 */
class SignupForm extends Model
{
    public $username;
    public $email;
    public $password;
    public $password_repeat;
    public $salutation;
    public $firstname;
    public $name;
    public $profession;
    public $workplace_type;
    public $workplace_name;
    public $address;
    public $city;
    public $zipcode;
    public $phone;
    public $mobile;
    public $riziv_number;
    public $apb_number;
    public $order_of_pharmacists_number;
    public $responsible_pneumologist;
    public $agree_user_terms;
    public $read_privacy_policy;
    public $profession_declaration;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['salutation', 'name', 'firstname', 'email', 'address', 'profession', 'username', 'password', 'agree_user_terms', 'read_privacy_policy', 'profession_declaration'], 'required'],
            [['name', 'firstname', 'email', 'address', 'zipcode', 'city', 'phone', 'mobile', 'username', 'workplace_name', 'responsible_pneumologist'], 'trim'],
            // Username has to be unique
            ['username', 'unique', 'targetClass' => 'infoweb\user\models\frontend\User', 'message' => Yii::t('infoweb/user', 'This username has already been taken.')],
            ['username', 'string', 'min' => 3, 'max' => 255],
            ['email', 'email'],
            // Emailaddress has to be unique
            ['email', 'unique', 'targetClass' => 'infoweb\user\models\frontend\User', 'message' => Yii::t('infoweb/user', 'This email address has already been taken.')],
            [['agree_user_terms', 'read_privacy_policy', 'profession_declaration'], 'compare', 'compareValue' => 1],
            // The password must contain at least one number and one symbol
            [['password', 'password_repeat'], 'match', 'pattern' => '/^(?=.*[0-9])(?=.*[!@#$%^&*])[A-Za-z0-9!@#$%^&*]{6,}$/'],
            // Passwords must match
            ['password', 'compare', 'compareAttribute' => 'password_repeat'],
            // Nurses and pneumologists must have a specific workplace_type
            ['workplace_type', 'in', 'range' => [Profile::WORKPLACETYPE_HOSPITAL, Profile::WORKPLACETYPE_PRIVATE], 'when' => function($model) {
                return in_array($model->profession, [Profile::PROFESSION_PNEUMOLOGIST, Profile::PROFESSION_NURSE]);
            }],
            // Nurses and pneumologists must have a workplace_name
            ['workplace_name', 'required', 'when' => function($model) {
                return in_array($model->profession, [Profile::PROFESSION_PNEUMOLOGIST, Profile::PROFESSION_NURSE]);
            }],
            // Pharmacists need an APB number
            ['apb_number', 'required', 'when' => function($model) {
                return $model->profession == Profile::PROFESSION_PHARMACIST;
            }],
            // All the rest needs a riziv number
            ['riziv_number', 'required', 'when' => function($model) {
                return !in_array($model->profession, [Profile::PROFESSION_PHARMACIST, '']);
            }]
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'username'                          => Yii::t('user', 'Username'),
            'email'                             => Yii::t('user', 'Email'),
            'password'                          => Yii::t('user', 'Password'),
            'password_repeat'                   => Yii::t('infoweb/user', 'Repeat password'),
            'salutation'                        => Yii::t('infoweb/user', 'Salutation'),
            'firstname'                         => Yii::t('infoweb/user', 'Firstname'),
            'name'                              => Yii::t('infoweb/user', 'Name'),
            'profession'                        => Yii::t('infoweb/user', 'Profession'),
            'workplace_type'                    => Yii::t('infoweb/user', 'Workplace'),
            'riziv_number'                      => Yii::t('infoweb/user', 'Riziv number'),
            'apb_number'                        => Yii::t('infoweb/user', 'APB number'),
            'agree_user_terms'                  => Yii::t('infoweb/user', 'I agree with the user-terms'),
            'read_privacy_policy'               => Yii::t('infoweb/user', 'I have read the privacy policy'),
            'profession_declaration'            => Yii::t('infoweb/user', 'I declare to be a registered doctor, nurse or pharmacist'),
        ];
    }

    /**
     * Signs user up.
     *
     * @return User|null the saved model or null if saving fails
     */
    public function signup()
    {
        if ($this->validate()) {
            
            $transaction = Yii::$app->db->beginTransaction();
            
            // Create the user
            $user = new User([
                'username'          => $this->username,
                'email'             => $this->email,
                'password_hash'     => Yii::$app->security->generatePasswordHash($this->password),
                'auth_key'          => Yii::$app->security->generateRandomString(),
                'scope'             => User::SCOPE_FRONTEND,
                'confirmed_at'      => time()
            ]);
            
            if ($user->save()) {
                
                // Create the profile
                $profile = new Profile([
                    'user_id'                       => $user->id,
                    'name'                          => $this->name,
                    'public_email'                  => $this->email,
                    'firstname'                     => $this->firstname,
                    'salutation'                    => $this->salutation,
                    'profession'                    => $this->profession,
                    'address'                       => $this->address,
                    'city'                          => $this->city,
                    'zipcode'                       => $this->zipcode,
                    'phone'                         => $this->phone,
                    'mobile'                        => $this->mobile,
                    'workplace_type'                => (in_array($this->profession, [Profile::PROFESSION_PNEUMOLOGIST, Profile::PROFESSION_NURSE])) ? $this->workplace_type : '',
                    'workplace_name'                => (in_array($this->profession, [Profile::PROFESSION_PNEUMOLOGIST, Profile::PROFESSION_NURSE])) ? $this->workplace_name : '',
                    'riziv_number'                  => ($this->profession != Profile::PROFESSION_PHARMACIST) ? $this->riziv_number : '',
                    'apb_number'                    => ($this->profession == Profile::PROFESSION_PHARMACIST) ? $this->apb_number : '',
                    'responsible_pneumologist'      => ($this->profession == Profile::PROFESSION_NURSE) ? $this->responsible_pneumologist : ''
                ]);
                
                if ($profile->save(false)) {
                    $transaction->commit();
                    return $user;
                }                                    
            }            
        }

        return null;
    }
}
