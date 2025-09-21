<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>エアコン工事申し込みフォーム</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ja.js"></script>
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
                <p class="info-text">カレンダーから空きのある日時を選択してください。第3希望まで選択可能です。</p>

                <div id="datetime-selection">
                    <div class="datetime-picker-container">
                        <div class="datetime-picker" id="datetime-picker"></div>
                        <div class="selected-slots">
                            <h4>選択された希望日時</h4>
                            <div id="selected-slots-list">
                                <p class="no-selection">希望日時を選択してください</p>
                            </div>
                        </div>
                    </div>

                    <!-- 隠しフィールド（フォーム送信用） -->
                    <input type="hidden" name="preferred_dates[]" id="hidden_date_1">
                    <input type="hidden" name="preferred_times[]" id="hidden_time_1">
                    <input type="hidden" name="preferred_dates[]" id="hidden_date_2">
                    <input type="hidden" name="preferred_times[]" id="hidden_time_2">
                    <input type="hidden" name="preferred_dates[]" id="hidden_date_3">
                    <input type="hidden" name="preferred_times[]" id="hidden_time_3">
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

            // 選択された日時を保存する配列
            let selectedSlots = [];
            let availableDates = new Set();

            // 利用可能な日付を取得
            function loadAvailableDates() {
                const today = new Date();
                const endDate = new Date();
                endDate.setMonth(today.getMonth() + 2); // 2ヶ月先まで

                // 30日分の利用可能日を確認
                const promises = [];
                for (let d = new Date(today); d <= endDate; d.setDate(d.getDate() + 1)) {
                    if (d > today) { // 明日以降のみ
                        const dateStr = d.toISOString().split('T')[0];
                        promises.push(
                            fetch('get_available_slots.php?date=' + dateStr)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success && data.slots.length > 0) {
                                        // 利用可能な時間帯がある場合のみカレンダーに表示
                                        const hasAvailableSlots = data.slots.some(slot => slot.available);
                                        if (hasAvailableSlots) {
                                            availableDates.add(dateStr);
                                        }
                                        return { date: dateStr, slots: data.slots };
                                    }
                                    return null;
                                })
                                .catch(() => null)
                        );
                    }
                }

                return Promise.all(promises).then(results => {
                    return results.filter(result => result !== null);
                });
            }

            // カレンダー初期化
            loadAvailableDates().then(availableData => {
                const calendar = flatpickr("#datetime-picker", {
                    locale: "ja",
                    inline: true,
                    minDate: "today",
                    maxDate: new Date().fp_incr(60), // 60日後まで
                    enable: Array.from(availableDates),
                    onChange: function(selectedDates, dateStr) {
                        if (selectedDates.length > 0) {
                            showTimeSlotSelection(dateStr, availableData);
                        }
                    }
                });
            });

            // 時間帯選択モーダル表示
            function showTimeSlotSelection(dateStr, availableData) {
                const dateData = availableData.find(d => d.date === dateStr);
                if (!dateData) return;

                // 既存のモーダルを削除
                const existingModal = document.querySelector('.time-slot-modal');
                if (existingModal) {
                    existingModal.remove();
                }

                // モーダル作成
                const modal = document.createElement('div');
                modal.className = 'time-slot-modal';
                modal.innerHTML = `
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>${new Date(dateStr).toLocaleDateString('ja-JP')} の時間帯を選択</h3>
                            <button class="modal-close">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="time-slot-options">
                                ${dateData.slots.map(slot => `
                                    <button class="time-slot-btn ${slot.available ? '' : 'unavailable'}"
                                            data-date="${dateStr}"
                                            data-slot="${slot.time_slot}"
                                            ${slot.available ? '' : 'disabled'}>
                                        ${slot.display_name}
                                        ${slot.available ? '' : '<span class="unavailable-text">（予約受付不可）</span>'}
                                    </button>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                `;

                document.body.appendChild(modal);

                // モーダルイベント
                modal.querySelector('.modal-close').addEventListener('click', () => {
                    modal.remove();
                });

                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.remove();
                    }
                });

                // 時間帯ボタンクリック
                modal.querySelectorAll('.time-slot-btn:not(.unavailable)').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const date = e.target.getAttribute('data-date');
                        const slot = e.target.getAttribute('data-slot');
                        addSelectedSlot(date, slot);
                        modal.remove();
                    });
                });
            }

            // 選択された日時を追加
            function addSelectedSlot(date, timeSlot) {
                // 重複チェック
                const exists = selectedSlots.some(slot =>
                    slot.date === date && slot.timeSlot === timeSlot
                );

                if (exists) {
                    alert('既に選択されている日時です。');
                    return;
                }

                if (selectedSlots.length >= 3) {
                    alert('最大3つまで選択できます。');
                    return;
                }

                // 時間帯名を取得
                const timeSlotNames = {
                    'morning': '午前（9:00-12:00）',
                    'afternoon': '午後（12:00-15:00）',
                    'evening': '夕方（15:00-18:00）'
                };

                selectedSlots.push({
                    date: date,
                    timeSlot: timeSlot,
                    displayName: timeSlotNames[timeSlot]
                });

                updateSelectedSlotsList();
                updateHiddenFields();
            }

            // 選択リスト更新
            function updateSelectedSlotsList() {
                const listContainer = document.getElementById('selected-slots-list');

                if (selectedSlots.length === 0) {
                    listContainer.innerHTML = '<p class="no-selection">希望日時を選択してください</p>';
                    return;
                }

                const html = selectedSlots.map((slot, index) => `
                    <div class="selected-slot-item">
                        <span class="slot-priority">第${index + 1}希望:</span>
                        <span class="slot-datetime">
                            ${new Date(slot.date).toLocaleDateString('ja-JP')} ${slot.displayName}
                        </span>
                        <button type="button" class="remove-slot-btn" data-index="${index}">削除</button>
                    </div>
                `).join('');

                listContainer.innerHTML = html;

                // 削除ボタンイベント
                listContainer.querySelectorAll('.remove-slot-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const index = parseInt(e.target.getAttribute('data-index'));
                        selectedSlots.splice(index, 1);
                        updateSelectedSlotsList();
                        updateHiddenFields();
                    });
                });
            }

            // 隠しフィールド更新
            function updateHiddenFields() {
                // 全ての隠しフィールドをクリア
                for (let i = 1; i <= 3; i++) {
                    document.getElementById(`hidden_date_${i}`).value = '';
                    document.getElementById(`hidden_time_${i}`).value = '';
                }

                // 選択された日時を設定
                selectedSlots.forEach((slot, index) => {
                    if (index < 3) {
                        document.getElementById(`hidden_date_${index + 1}`).value = slot.date;
                        document.getElementById(`hidden_time_${index + 1}`).value = slot.timeSlot;
                    }
                });
            }

            // フォーム送信時のバリデーション
            document.getElementById('applicationForm').addEventListener('submit', function(e) {
                if (selectedSlots.length === 0) {
                    e.preventDefault();
                    alert('希望日時を少なくとも1つ選択してください。');
                    return false;
                }
            });
        });
    </script>
</body>
</html>