<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "cart".
 *
 * @property integer $id
 * @property integer $customer_id
 * @property string $session_id
 * @property string $created
 * @property string $updated
 * @property integer $status
 */
class Cart extends \yii\db\ActiveRecord {

    const CART_PENDING = 0x000;
    const CART_SOLD = 0x001;
    const CART_REFUNDED = 0x002;

    public $quantity = 0;
    public $subtotal = 0;
    public $total = 0;
    public $fees = 0;
    public $stripe_fee = 0;

    /**
     * @inheritdoc
     */
    public static function tableName() {
        return 'cart';
    }

    /**
     * @inheritdoc
     */
    public function rules() {
        return [
            [['customer_id', 'session_id', 'created', 'status'], 'required'],
            [['customer_id', 'status'], 'integer'],
            [['created', 'updated'], 'safe'],
            [['session_id'], 'string', 'max' => 100]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels() {
        return [
            'id' => 'Cart ID',
            'customer_id' => 'Customer User ID',
            'session_id' => 'Session ID',
            'created' => 'Time created',
            'updated' => 'Time last updated',
            'status' => 'Status',
        ];
    }

    public static function getCurrentCart() {
        if (($cart = self::findOne(['customer_id' => \Yii::$app->user->identity->id, 'session_id' => session_id(), 'status' => self::CART_PENDING])) !== null) {
            return $cart;
        }
        $cart = new Cart();
        $cart->customer_id = \Yii::$app->user->identity->id;
        $cart->session_id = session_id();
        $cart->created = date('Y-m-d H:i:s');
        $cart->status = self::CART_PENDING;
        $cart->save();
        return $cart;
    }

    public function addItem($id) {
        if (($cart_item = CartItems::findOne(['cart_id' => $this->id, 'ticket_id' => $id])) === null) {
            $cart_item = new CartItems();
            $cart_item->quantity = 0;
        }
        $cart_item->cart_id = $this->id;
        $cart_item->ticket_id = $id;
        $cart_item->quantity++;
        $cart_item->save();
    }

    public function getItems() {
        return $this->hasMany(CartItems::className(), ['cart_id' => 'id']);
    }

    public function getCustomer() {
        return $this->hasOne(User::className(), ['id', 'customer_id']);
    }

    public function processCart() {
        $this->quantity = 0;
        $this->subtotal = 0;
        $this->fees = 0;
        foreach ($this->items as $item) {
            $this->quantity += $item->quantity;
            $this->subtotal += $item->quantity * $item->ticket->ticket_price;
            $this->fees += $item->quantity * $item->ticket->ticket_fee;
        }
        $this->stripe_fee = round(0.015 * $this->subtotal + 0.2, 2);
        $this->fees += $this->stripe_fee;
        $this->total = $this->subtotal + $this->stripe_fee;
    }

}
