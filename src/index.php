<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>エアコン工事申し込みフォーム</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>エアコン工事申し込みフォーム</h1>
            <p>エアコンの設置工事をご希望のお客様は、以下のフォームにご記入ください。</p>
        </header>

        <form action="process_form.php" method="POST" id="applicationForm">
            <section class="form-section">
                <h2>お客様情報</h2>
                <div class="form-group">
                    <label for="customer_name">お名前 <span class="required">*</span></label>
                    <input type="text" id="customer_name" name="customer_name" required>
                </div>

                <div class="form-group">
                    <label for="customer_phone">電話番号 <span class="required">*</span></label>
                    <input type="tel" id="customer_phone" name="customer_phone" required>
                </div>

                <div class="form-group">
                    <label for="customer_email">メールアドレス</label>
                    <input type="email" id="customer_email" name="customer_email">
                </div>
            </section>

            <section class="form-section">
                <h2>設置場所情報</h2>
                <div class="form-group">
                    <label for="postal_code">郵便番号 <span class="required">*</span></label>
                    <input type="text" id="postal_code" name="postal_code" pattern="\d{3}-\d{4}" placeholder="123-4567" required>
                </div>

                <div class="form-group">
                    <label for="address">住所 <span class="required">*</span></label>
                    <textarea id="address" name="address" rows="3" required></textarea>
                </div>

                <div class="form-group">
                    <label for="building_type">建物種別 <span class="required">*</span></label>
                    <select id="building_type" name="building_type" required>
                        <option value="">選択してください</option>
                        <option value="house">一戸建て</option>
                        <option value="apartment">アパート・マンション</option>
                        <option value="office">オフィス</option>
                        <option value="store">店舗</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="floor_number">階数</label>
                    <input type="number" id="floor_number" name="floor_number" min="1" max="50">
                </div>
            </section>

            <section class="form-section">
                <h2>エアコン設置詳細</h2>
                <div class="form-group">
                    <label for="room_type">設置予定の部屋 <span class="required">*</span></label>
                    <select id="room_type" name="room_type" required>
                        <option value="">選択してください</option>
                        <option value="living">リビング・居間</option>
                        <option value="bedroom">寝室</option>
                        <option value="kitchen">キッチン・台所</option>
                        <option value="office">書斎・オフィス</option>
                        <option value="other">その他</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="room_size">部屋の広さ <span class="required">*</span></label>
                    <select id="room_size" name="room_size" required>
                        <option value="">選択してください</option>
                        <option value="6jo">6畳まで</option>
                        <option value="8jo">8畳まで</option>
                        <option value="10jo">10畳まで</option>
                        <option value="12jo">12畳まで</option>
                        <option value="14jo">14畳まで</option>
                        <option value="16jo">16畳まで</option>
                        <option value="18jo_over">18畳以上</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="ac_type">エアコンの種類 <span class="required">*</span></label>
                    <select id="ac_type" name="ac_type" required>
                        <option value="">選択してください</option>
                        <option value="wall_mounted">壁掛け型</option>
                        <option value="ceiling_cassette">天井カセット型</option>
                        <option value="floor_standing">床置き型</option>
                        <option value="ceiling_concealed">天井埋込型</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="ac_capacity">エアコンの能力 <span class="required">*</span></label>
                    <select id="ac_capacity" name="ac_capacity" required>
                        <option value="">選択してください</option>
                        <option value="2.2kw">2.2kW</option>
                        <option value="2.5kw">2.5kW</option>
                        <option value="2.8kw">2.8kW</option>
                        <option value="3.6kw">3.6kW</option>
                        <option value="4.0kw">4.0kW</option>
                        <option value="5.6kw">5.6kW</option>
                        <option value="7.1kw">7.1kW</option>
                    </select>
                </div>
            </section>

            <section class="form-section">
                <h2>工事詳細</h2>
                <div class="form-group">
                    <label for="existing_ac">既設エアコンの有無 <span class="required">*</span></label>
                    <div class="radio-group">
                        <label><input type="radio" name="existing_ac" value="yes" required> あり</label>
                        <label><input type="radio" name="existing_ac" value="no" required> なし</label>
                    </div>
                </div>

                <div class="form-group" id="removal_group" style="display: none;">
                    <label for="existing_ac_removal">既設エアコンの撤去</label>
                    <div class="radio-group">
                        <label><input type="radio" name="existing_ac_removal" value="yes"> 撤去希望</label>
                        <label><input type="radio" name="existing_ac_removal" value="no" checked> 撤去不要</label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="electrical_work">電気工事 <span class="required">*</span></label>
                    <select id="electrical_work" name="electrical_work" required>
                        <option value="">選択してください</option>
                        <option value="none">不要</option>
                        <option value="outlet_addition">コンセント増設</option>
                        <option value="voltage_change">電圧変更</option>
                        <option value="circuit_addition">回路増設</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="piping_work">配管工事 <span class="required">*</span></label>
                    <select id="piping_work" name="piping_work" required>
                        <option value="">選択してください</option>
                        <option value="new">新規配管</option>
                        <option value="existing_reuse">既設配管再利用</option>
                        <option value="partial_replacement">一部配管交換</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="wall_drilling">壁穴あけ工事 <span class="required">*</span></label>
                    <div class="radio-group">
                        <label><input type="radio" name="wall_drilling" value="yes" required> 必要</label>
                        <label><input type="radio" name="wall_drilling" value="no" required> 不要</label>
                    </div>
                </div>
            </section>

            <section class="form-section">
                <h2>希望日時</h2>
                <div class="form-group">
                    <label for="preferred_date">希望日</label>
                    <input type="date" id="preferred_date" name="preferred_date">
                </div>

                <div class="form-group">
                    <label for="preferred_time">希望時間帯</label>
                    <select id="preferred_time" name="preferred_time">
                        <option value="flexible">指定なし</option>
                        <option value="morning">午前中</option>
                        <option value="afternoon">午後</option>
                        <option value="evening">夕方</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="special_requests">特記事項・ご要望</label>
                    <textarea id="special_requests" name="special_requests" rows="4" placeholder="その他ご要望やご質問等ございましたらご記入ください"></textarea>
                </div>
            </section>

            <div class="form-actions">
                <button type="submit" class="submit-btn">申し込み内容を送信</button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const existingAcRadios = document.querySelectorAll('input[name="existing_ac"]');
            const removalGroup = document.getElementById('removal_group');

            existingAcRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'yes') {
                        removalGroup.style.display = 'block';
                    } else {
                        removalGroup.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>