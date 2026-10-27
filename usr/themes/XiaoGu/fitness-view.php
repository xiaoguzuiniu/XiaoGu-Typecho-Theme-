<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$fitnessDates = [];
$fitnessError = '';
try {
    $fitnessDates = xiaoguFitnessCompletedDates();
} catch (\Throwable $error) {
    $fitnessError = '完成记录暂时无法读取';
    error_log('[fitness-page] ' . $error->getMessage());
}
$fitnessConfig = [
    'completedDates' => $fitnessDates,
    'endpoint' => rtrim((string) $this->options->siteUrl, '/') . '/api/fitness',
];
$fitnessImageOrigin = getXiaoGuQiniuConfiguredOrigin() ?: 'https://img.gulook.site';
$fitnessImageKeys = [
    'monday-floor-press' => 'typecho/2026/10/090954-225f985533d6f8c42938abe3.png',
    'monday-shoulder-press' => 'typecho/2026/10/091023-a52c67fd5f631c99857e9a1e.png',
    'monday-lateral-raise' => 'typecho/2026/10/091023-962d24e692fced471282638a.png',
    'monday-push-up' => 'typecho/2026/10/091023-9a051e1df7be5155b527f27b.png',
    'tuesday-goblet-squat' => 'typecho/2026/10/091023-093456c5b5f675ea9a7fb638.png',
    'tuesday-romanian-deadlift' => 'typecho/2026/10/091024-84f4182fc2bc5e5a9732cfb9.png',
    'tuesday-glute-bridge' => 'typecho/2026/10/091024-b1ff6c5c1fe38130175c54b6.png',
    'tuesday-calf-raise' => 'typecho/2026/10/091024-d8ddb2ee9ad90f0cc173871d.png',
    'tuesday-dead-bug' => 'typecho/2026/10/091024-737d3caa49e9d12a35e5cd85.png',
    'thursday-row' => 'typecho/2026/10/091025-271745aad90342ab6e363b19.png',
    'thursday-shrug' => 'typecho/2026/10/091025-05dd40509d654d3829bd6b7e.png',
    'thursday-reverse-fly' => 'typecho/2026/10/091025-e66b05e226d4d7ef34a53e34.png',
    'thursday-curl' => 'typecho/2026/10/091025-2e8a208b9fd98338c1379112.png',
    'friday-reverse-lunge' => 'typecho/2026/10/091026-2c13f5e3be9664d511081a6b.png',
    'friday-single-leg-rdl' => 'typecho/2026/10/091026-90f5696b664cb09a5dcd1d37.png',
    'friday-weighted-glute-bridge' => 'typecho/2026/10/091026-2d88de97b18a39a05ce4ca6f.png',
    'friday-side-plank' => 'typecho/2026/10/091026-a2dfcb66c9fd10a1582b1694.png',
];
$fitnessPlans = [
    'monday' => [
        ['image' => 'monday-floor-press', 'name' => '哑铃地板卧推', 'sets' => '3 组 × 8–15 次', 'rest' => '休息 90–120 秒', 'description' => '仰卧在瑜伽垫上，双手持哑铃，缓慢下降至上臂轻触地面，再向上推起。不要用腰部反弓借力。'],
        ['image' => 'monday-shoulder-press', 'name' => '哑铃肩上推举', 'sets' => '2 组 × 8–12 次', 'rest' => '休息 90–120 秒', 'description' => '站姿或坐在稳固的无靠背坐垫上完成。收紧腹部，避免腰部过度后仰。'],
        ['image' => 'monday-lateral-raise', 'name' => '哑铃侧平举', 'sets' => '2 组 × 12–20 次', 'rest' => '休息 60–90 秒', 'description' => '使用轻重量，手肘微弯，缓慢抬起。这个动作对肩部中束的训练很重要。'],
        ['image' => 'monday-push-up', 'name' => '俯卧撑', 'sets' => '2 组 × 6–12 次', 'rest' => '休息 60–90 秒', 'description' => '如果标准俯卧撑做不到 6 次，可改为跪姿俯卧撑。身体保持一条直线，动作范围以肩部舒适为准。'],
    ],
    'tuesday' => [
        ['image' => 'tuesday-goblet-squat', 'name' => '哑铃高脚杯深蹲', 'sets' => '3 组 × 10–15 次', 'rest' => '休息 90–120 秒', 'description' => '双手在胸前抱住一只哑铃，稳定下蹲再站起。初期可以先用 4–8 kg。'],
        ['image' => 'tuesday-romanian-deadlift', 'name' => '哑铃罗马尼亚硬拉', 'sets' => '3 组 × 10–15 次', 'rest' => '休息 90–120 秒', 'description' => '两只哑铃贴近大腿下放，臀部向后移动，背部保持稳定。先用每只 3–5 kg 熟悉髋部折叠。'],
        ['image' => 'tuesday-glute-bridge', 'name' => '臀桥', 'sets' => '2 组 × 12–20 次', 'rest' => '休息 60–90 秒', 'description' => '仰卧屈膝，抬起臀部至身体基本成直线。熟练后可将轻哑铃放在髋部增加阻力。'],
        ['image' => 'tuesday-calf-raise', 'name' => '提踵', 'sets' => '2 组 × 15–25 次', 'rest' => '休息 45–60 秒', 'description' => '缓慢踮起脚跟，再控制下降。先徒手练习，稳定后再手持哑铃。'],
        ['image' => 'tuesday-dead-bug', 'name' => 'Dead Bug 死虫式', 'sets' => '2 组 × 每侧 8–10 次', 'rest' => '休息 45–60 秒', 'description' => '仰卧交替伸展对侧手脚，保持腰部稳定，不要为了伸得更远而塌腰。'],
    ],
    'thursday' => [
        ['image' => 'thursday-row', 'name' => '单臂哑铃划船', 'sets' => '3 组 × 每侧 10–15 次', 'rest' => '休息 60–90 秒', 'description' => '一手支撑在同侧大腿上，另一只手将哑铃拉向髋部。不要耸肩或扭转躯干，初期每只可试 4–8 kg。'],
        ['image' => 'thursday-shrug', 'name' => '哑铃耸肩', 'sets' => '3 组 × 10–15 次', 'rest' => '休息 60–90 秒', 'description' => '上臂靠近身体，控制哑铃上升和下降，不要用身体摆动借力。初期每只可试 2–4 kg。'],
        ['image' => 'thursday-reverse-fly', 'name' => '俯身哑铃反向飞鸟', 'sets' => '2 组 × 12–20 次', 'rest' => '休息 60–90 秒', 'description' => '髋部向后折叠，上身前倾，用轻重量向两侧打开手臂，训练三角肌后束。初期每只可试 1–2 kg。'],
        ['image' => 'thursday-curl', 'name' => '哑铃锤式弯举', 'sets' => '2 组 × 10–15 次', 'rest' => '休息 60 秒', 'description' => '掌心相对，像握锤子一样弯举，让手臂整体更饱满。初期每只可试 2–4 kg。'],
    ],
    'friday' => [
        ['image' => 'friday-reverse-lunge', 'name' => '反向弓步', 'sets' => '3 组 × 每侧 8–12 次', 'rest' => '休息 90 秒', 'description' => '先徒手练习稳定性，再逐渐加入哑铃。动作不稳时不要急着加重。'],
        ['image' => 'friday-single-leg-rdl', 'name' => '单腿罗马尼亚硬拉', 'sets' => '2 组 × 每侧 8–12 次', 'rest' => '休息 60–90 秒', 'description' => '先徒手练习，保持骨盆稳定，髋部向后移动。平衡不足时，一只手扶墙即可。'],
        ['image' => 'friday-weighted-glute-bridge', 'name' => '负重臀桥', 'sets' => '3 组 × 12–20 次', 'rest' => '休息 60–90 秒', 'description' => '在髋部放置固定可靠的轻哑铃，顶端收紧臀部，但不要过度挺腰。'],
        ['image' => 'friday-side-plank', 'name' => '侧平板支撑', 'sets' => '2 组 × 每侧 20–40 秒', 'rest' => '休息 45–60 秒', 'description' => '身体保持稳定，不要让骨盆下沉。初期可以屈膝支撑。'],
    ],
];
?>

<div class="fitness-page" id="fitness-page">
    <section class="fitness-character" aria-label="个人像素形象">
        <svg viewBox="0 0 80 112" aria-hidden="true" focusable="false" shape-rendering="crispEdges">
            <path fill="#33385d" d="M24 4h32v8H24zM16 12h48v24H16zM24 36h32v8H24z"/>
            <path fill="#f7c69d" d="M24 20h32v32H24zM16 28h8v16h-8zM56 28h8v16h-8z"/>
            <path fill="#33385d" d="M28 28h8v8h-8zM44 28h8v8h-8z"/>
            <path fill="#de9279" d="M32 44h16v4H32z"/>
            <path fill="#5759e8" d="M20 52h40v36H20zM12 60h8v24h-8zM60 60h8v24h-8z"/>
            <path fill="#e0e5ff" d="M36 52h8v12h-8z"/>
            <path fill="#343b71" d="M24 88h16v16H24zM40 88h16v16H40z"/>
            <path fill="#242b50" d="M16 104h24v8H16zM40 104h24v8H40z"/>
        </svg>
    </section>

    <section class="fitness-calendar fitness-panel" aria-label="健身日历">
        <div class="fitness-panel-head">
            <h2 id="fitness-month-title"></h2>
            <div class="fitness-month-actions">
                <button type="button" id="fitness-prev-month" aria-label="上个月">‹</button>
                <button type="button" id="fitness-today" aria-label="回到今天">今天</button>
                <button type="button" id="fitness-next-month" aria-label="下个月">›</button>
            </div>
        </div>
        <div class="fitness-weekdays" aria-hidden="true"><span>一</span><span>二</span><span>三</span><span>四</span><span>五</span><span>六</span><span>日</span></div>
        <div class="fitness-calendar-days" id="fitness-calendar-days" aria-label="选择训练日期"></div>
        <?php if ($fitnessError !== ''): ?><p class="fitness-message" role="status"><?php echo htmlspecialchars($fitnessError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    </section>

    <section class="fitness-workout fitness-panel" aria-label="当天训练安排">
        <div class="fitness-workout-content" id="fitness-workout-content" aria-label="训练动作列表" aria-live="polite" tabindex="0"></div>
    </section>
</div>

<dialog class="fitness-checkin-dialog" id="fitness-checkin-dialog" aria-labelledby="fitness-checkin-title">
    <div class="fitness-checkin-dialog-head">
        <div><span class="fitness-panel-label">打卡详情</span><h2 id="fitness-checkin-title"></h2></div>
        <button type="button" id="fitness-checkin-close" aria-label="关闭打卡详情">×</button>
    </div>
    <div class="fitness-checkin-dialog-body" id="fitness-checkin-content" aria-live="polite"></div>
</dialog>

<?php foreach ($fitnessPlans as $day => $exercises): ?>
    <template id="fitness-<?php echo $day; ?>-template">
        <ol class="fitness-exercise-list">
            <?php foreach ($exercises as $index => $exercise): ?>
                <?php
                $descriptionId = 'fitness-' . $day . '-description-' . ($index + 1);
                $imageUrl = rtrim($fitnessImageOrigin, '/') . '/' . $fitnessImageKeys[$exercise['image']]
                    . '?imageView2/2/w/232/h/208/q/80/format/webp';
                ?>
                <li class="fitness-exercise">
                    <div class="fitness-exercise-art">
                        <img src="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8'); ?>"
                             alt="<?php echo htmlspecialchars($exercise['name'] . '动作示范', ENT_QUOTES, 'UTF-8'); ?>"
                             loading="lazy" decoding="async">
                    </div>
                    <div class="fitness-exercise-copy">
                        <h3><span><?php echo sprintf('%02d', $index + 1); ?></span><?php echo htmlspecialchars($exercise['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <div class="fitness-exercise-stats">
                            <strong><?php echo htmlspecialchars($exercise['sets'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span><?php echo htmlspecialchars($exercise['rest'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <p class="fitness-exercise-description" id="<?php echo $descriptionId; ?>"><?php echo htmlspecialchars($exercise['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <button class="fitness-exercise-toggle" type="button" aria-expanded="false" aria-controls="<?php echo $descriptionId; ?>">展开要点</button>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </template>
<?php endforeach; ?>
<template id="fitness-wednesday-template">
    <div class="fitness-recovery">
        <p>这一天不再安排高强度力量训练，让身体适应每周训练的节奏。</p>
        <table aria-label="周三核心与恢复训练">
            <thead><tr><th scope="col">动作</th><th scope="col">训练量</th><th scope="col">休息</th></tr></thead>
            <tbody>
                <tr><td>平板支撑</td><td>2 × 20–40 秒</td><td>45 秒</td></tr>
                <tr><td>鸟狗式</td><td>2 × 每侧 8 次</td><td>45 秒</td></tr>
                <tr><td>徒手深蹲</td><td>2 × 12 次</td><td>45 秒</td></tr>
                <tr><td>肩部绕环、髋部活动</td><td>5–8 分钟</td><td>按需</td></tr>
                <tr><td>轻松散步</td><td>10–20 分钟</td><td>—</td></tr>
            </tbody>
        </table>
        <p>所有动作保持轻松，不练到力竭。如果周一、周二的肌肉酸痛明显，这一天可以直接改为散步和活动度练习。</p>
    </div>
</template>
<template id="fitness-empty-template">
    <div class="fitness-workout-empty"><span aria-hidden="true">◇</span><strong>今天没有固定训练安排</strong><p>可以休息，或做些轻松活动。</p></div>
</template>
<script type="application/json" id="fitness-config"><?php echo json_encode($fitnessConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
