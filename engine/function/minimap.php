<?php

const ZNOTE_MINIMAP_DIR       = 'engine/minimap';
const ZNOTE_MINIMAP_META      = 'engine/minimap/minimap.json';
const ZNOTE_MINIMAP_SIGNATURE = 0x4D4D544F;
const ZNOTE_MINIMAP_VERSION   = 1;
const ZNOTE_MINIMAP_BLOCK     = 64;
const ZNOTE_MINIMAP_BLOCK_LEN = 12288;
const ZNOTE_MINIMAP_MAX_BLOCK = 20000;
const ZNOTE_MINIMAP_MAX_BYTES = 16777216;

class ZnoteOtmmReader
{
	protected string $data;
	protected int $offset = 0;
	protected int $length;

	public function __construct(string $data)
	{
		$this->data   = $data;
		$this->length = strlen($data);
	}

	public function seek(int $offset): void
	{
		if ($offset < 0 || $offset > $this->length) {
			throw new RuntimeException('Invalid offset inside the .otmm file.');
		}
		$this->offset = $offset;
	}

	public function eof(): bool
	{
		return $this->offset >= $this->length;
	}

	public function remaining(): int
	{
		return $this->length - $this->offset;
	}

	public function u8(): int
	{
		$this->need(1);
		return ord($this->data[$this->offset++]);
	}

	public function u16(): int
	{
		$this->need(2);
		$value = unpack('v', substr($this->data, $this->offset, 2))[1];
		$this->offset += 2;
		return $value;
	}

	public function u32(): int
	{
		$this->need(4);
		$value = unpack('V', substr($this->data, $this->offset, 4))[1];
		$this->offset += 4;
		return $value;
	}

	public function string(): string
	{
		return $this->bytes($this->u16());
	}

	public function bytes(int $length): string
	{
		$this->need($length);
		$value = substr($this->data, $this->offset, $length);
		$this->offset += $length;
		return $value;
	}

	protected function need(int $bytes): void
	{
		if ($bytes < 0 || $this->remaining() < $bytes) {
			throw new RuntimeException('Unexpected end of the .otmm file.');
		}
	}
}

function minimap_root(): string
{
	return dirname(__DIR__, 2) . '/' . ZNOTE_MINIMAP_DIR;
}

function minimap_meta_path(): string
{
	return dirname(__DIR__, 2) . '/' . ZNOTE_MINIMAP_META;
}

function minimap_data()
{
	static $data = null;
	if ($data !== null) {
		return $data;
	}

	$data = false;
	$file = minimap_meta_path();
	if (!is_file($file)) {
		return $data;
	}

	$decoded = json_decode((string)file_get_contents($file), true);
	if (!is_array($decoded) || empty($decoded['floors'])) {
		return $data;
	}

	ksort($decoded['floors'], SORT_NUMERIC);
	$data = $decoded;

	return $data;
}

function minimap_available(): bool
{
	return minimap_data() !== false;
}

function minimap_color(int $color): array
{
	if ($color >= 0 && $color < 216) {
		return array(
			(int)(floor($color / 36) * 51),
			(int)(floor(($color % 36) / 6) * 51),
			(int)(($color % 6) * 51)
		);
	}

	return array(0, 0, 0);
}

function minimap_tile_image(string $raw, bool &$hasVisible)
{
	$image = imagecreatetruecolor(ZNOTE_MINIMAP_BLOCK, ZNOTE_MINIMAP_BLOCK);
	imagealphablending($image, false);
	imagesavealpha($image, true);
	imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

	$cache      = array();
	$hasVisible = false;

	for ($index = 0; $index < ZNOTE_MINIMAP_BLOCK * ZNOTE_MINIMAP_BLOCK; $index++) {
		$offset = $index * 3;
		$flags  = ord($raw[$offset]);
		$color  = ord($raw[$offset + 1]);

		if (!($flags & 1) || ($flags & 8) || $color === 255) {
			continue;
		}
		if (!isset($cache[$color])) {
			list($r, $g, $b) = minimap_color($color);
			$cache[$color]   = imagecolorallocatealpha($image, $r, $g, $b, 0);
		}

		imagesetpixel($image, $index % ZNOTE_MINIMAP_BLOCK, (int)floor($index / ZNOTE_MINIMAP_BLOCK), $cache[$color]);
		$hasVisible = true;
	}

	return $image;
}

function minimap_delete(): void
{
	foreach (glob(minimap_root() . '/*.png') ?: array() as $tile) {
		@unlink($tile);
	}
	@unlink(minimap_meta_path());
}

function minimap_import(string $tmpFile, string $originalName, ?string &$error = null): bool
{
	$error = null;

	if (strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION)) !== 'otmm') {
		$error = 'Only .otmm minimap files are accepted.';
		return false;
	}
	if (!is_file($tmpFile) || filesize($tmpFile) < 16) {
		$error = 'The uploaded file is empty or unreadable.';
		return false;
	}
	if (filesize($tmpFile) > ZNOTE_MINIMAP_MAX_BYTES) {
		$error = 'The .otmm file must be ' . (int)(ZNOTE_MINIMAP_MAX_BYTES / 1048576) . ' MB or smaller.';
		return false;
	}
	if (!function_exists('gzuncompress') || !function_exists('imagecreatetruecolor')) {
		$error = 'PHP needs the zlib and GD extensions to convert an .otmm minimap.';
		return false;
	}

	$root = minimap_root();
	if (!is_dir($root) && !@mkdir($root, 0755, true) && !is_dir($root)) {
		$error = 'Could not create ' . ZNOTE_MINIMAP_DIR . '/. Give the web server write access.';
		return false;
	}
	if (!is_writable($root)) {
		$error = ZNOTE_MINIMAP_DIR . '/ is not writable by the web server.';
		return false;
	}

	$data = file_get_contents($tmpFile);
	if ($data === false) {
		$error = 'Could not read the uploaded file.';
		return false;
	}

	@set_time_limit(600);

	$floors = array();
	$tiles  = 0;

	try {
		$reader = new ZnoteOtmmReader($data);

		if ($reader->u32() !== ZNOTE_MINIMAP_SIGNATURE) {
			$error = 'This is not a valid OTMM minimap file.';
			return false;
		}

		$start   = $reader->u16();
		$version = $reader->u16();
		$reader->u32();

		if ($version !== ZNOTE_MINIMAP_VERSION) {
			$error = 'Unsupported OTMM minimap version.';
			return false;
		}

		$reader->string();
		$reader->seek($start);

		minimap_delete();

		$blocks = 0;

		while (!$reader->eof()) {
			if (++$blocks > ZNOTE_MINIMAP_MAX_BLOCK) {
				minimap_delete();
				$error = 'The .otmm minimap contains too many blocks.';
				return false;
			}
			if ($reader->remaining() < 7) {
				break;
			}

			$x = $reader->u16();
			$y = $reader->u16();
			$z = $reader->u8();

			if ($x >= 65535 || $y >= 65535 || $z > 15) {
				break;
			}

			$length = $reader->u16();
			if ($length <= 0 || $length > $reader->remaining()) {
				minimap_delete();
				$error = 'The .otmm minimap contains a corrupt block.';
				return false;
			}

			$raw = @gzuncompress($reader->bytes($length), ZNOTE_MINIMAP_BLOCK_LEN);
			if (!is_string($raw) || strlen($raw) !== ZNOTE_MINIMAP_BLOCK_LEN) {
				continue;
			}

			$hasVisible = false;
			$image      = minimap_tile_image($raw, $hasVisible);

			if (!$hasVisible) {
				imagedestroy($image);
				continue;
			}

			imagepng($image, $root . '/z' . $z . '-' . $x . '-' . $y . '.png', 6);
			imagedestroy($image);
			$tiles++;

			if (!isset($floors[$z])) {
				$floors[$z] = array(
					'min_x' => $x,
					'min_y' => $y,
					'max_x' => $x + 63,
					'max_y' => $y + 63,
					'tiles' => array()
				);
			}

			$floors[$z]['min_x']   = min($floors[$z]['min_x'], $x);
			$floors[$z]['min_y']   = min($floors[$z]['min_y'], $y);
			$floors[$z]['max_x']   = max($floors[$z]['max_x'], $x + 63);
			$floors[$z]['max_y']   = max($floors[$z]['max_y'], $y + 63);
			$floors[$z]['tiles'][] = array($x, $y);
		}
	} catch (Throwable $e) {
		minimap_delete();
		$error = 'Could not read the .otmm minimap: ' . $e->getMessage();
		return false;
	}

	if (!$floors) {
		minimap_delete();
		$error = 'No visible minimap tiles were found in this .otmm file.';
		return false;
	}

	ksort($floors, SORT_NUMERIC);

	$prepared = array();
	foreach ($floors as $z => $floor) {
		$prepared[(int)$z] = array(
			'z' => (int)$z,
			'x' => (int)$floor['min_x'],
			'y' => (int)$floor['min_y'],
			'w' => max(64, (int)($floor['max_x'] - $floor['min_x'] + 1)),
			'h' => max(64, (int)($floor['max_y'] - $floor['min_y'] + 1)),
			't' => $floor['tiles']
		);
	}

	$meta = array(
		'date'   => time(),
		'source' => substr((string)preg_replace('/[^A-Za-z0-9._-]/', '', basename($originalName)), 0, 80),
		'tiles'  => $tiles,
		'floors' => $prepared
	);

	if (file_put_contents(minimap_meta_path(), json_encode($meta)) === false) {
		minimap_delete();
		$error = 'Could not write ' . ZNOTE_MINIMAP_META . '.';
		return false;
	}

	return true;
}


function minimap_was_rendered(): bool
{
	return !empty($GLOBALS['__znote_minimap_rendered']);
}

function minimap_render(string $baseUrl = '', bool $withTitle = true): void
{
	if (minimap_was_rendered()) {
		return;
	}

	$data = minimap_data();
	if ($data === false) {
		return;
	}

	$GLOBALS['__znote_minimap_rendered'] = true;

	$floors = array();
	foreach ($data['floors'] as $floor) {
		$floors[] = array(
			'z' => (int)$floor['z'],
			'x' => (int)$floor['x'],
			'y' => (int)$floor['y'],
			't' => $floor['t']
		);
	}

	$payload = array(
		'base'   => (($baseUrl !== '') ? rtrim($baseUrl, '/') . '/' : '') . ZNOTE_MINIMAP_DIR . '/',
		'v'      => (int)($data['date'] ?? 0),
		'start'  => 7,
		'floors' => $floors
	);
	?>
	<div class="znote-minimap" id="znoteMinimap">
		<?php if ($withTitle): ?><h2 class="znote-minimap-title">World map</h2><?php endif; ?>
		<div class="znote-minimap-stage">
			<div class="znote-minimap-canvas"></div>
			<div class="znote-minimap-arrows">
				<button type="button" class="znote-minimap-btn" data-minimap="floor-up" title="Floor up">&#9650;</button>
				<span class="znote-minimap-level">Floor <b>7</b></span>
				<button type="button" class="znote-minimap-btn" data-minimap="floor-down" title="Floor down">&#9660;</button>
			</div>
			<div class="znote-minimap-zoom">
				<button type="button" class="znote-minimap-btn" data-minimap="zoom-in" title="Zoom in">+</button>
				<button type="button" class="znote-minimap-btn" data-minimap="zoom-out" title="Zoom out">&minus;</button>
			</div>
		</div>
		<p class="znote-minimap-hint">Drag to move, scroll or use +/&minus; to zoom, and change floor with the arrows.</p>
	</div>
	<style>
	.znote-minimap { margin: 0 0 20px; }
	.znote-minimap-title { margin: 0 0 8px; }
	.znote-minimap-stage {
		position: relative;
		height: 460px;
		overflow: hidden;
		cursor: grab;
		touch-action: none;
		background: var(--bg-default, rgb(15,17,20));
		border: 1px solid var(--border, rgb(19,20,23));
	}
	.znote-minimap-stage.is-dragging { cursor: grabbing; }
	.znote-minimap-canvas { position: absolute; left: 0; top: 0; right: 0; bottom: 0; }
	/* max-width/height come back explicitly: a CSS reset like Tailwind's
	   preflight sets img{max-width:100%;height:auto}, which would collapse
	   every tile the moment this sits in a themed page. */
	.znote-minimap-canvas img {
		position: absolute;
		max-width: none;
		max-height: none;
		min-width: 0;
		min-height: 0;
		image-rendering: pixelated;
		user-select: none;
		pointer-events: none;
	}
	.znote-minimap-arrows,
	.znote-minimap-zoom {
		position: absolute;
		display: flex;
		flex-direction: column;
		gap: 4px;
		z-index: 2;
	}
	.znote-minimap-arrows { top: 10px; right: 10px; align-items: center; }
	.znote-minimap-zoom { bottom: 10px; right: 10px; }
	.znote-minimap-btn {
		width: 32px;
		height: 32px;
		padding: 0;
		line-height: 1;
		font-size: 14px;
		cursor: pointer;
		color: var(--font-color, rgb(155,162,177));
		background: var(--primary, rgb(30,33,40));
		border: 1px solid var(--border, rgb(19,20,23));
	}
	.znote-minimap-btn:hover { color: var(--anchor-hover, #e79424); }
	.znote-minimap-level {
		padding: 2px 6px;
		font-size: 11px;
		white-space: nowrap;
		color: var(--font-color, rgb(155,162,177));
		background: var(--secondary, rgb(25,28,33));
		border: 1px solid var(--border, rgb(19,20,23));
	}
	.znote-minimap-hint { margin: 6px 0 0; font-size: 12px; opacity: .7; }
	</style>
	<script>
	(function () {
		var data = <?php echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

		function init() {
		var viewer = document.getElementById('znoteMinimap');
		if (!viewer || viewer.dataset.ready) return;
		viewer.dataset.ready = '1';

		if (!data || !data.floors || !data.floors.length) return;

		var stage  = viewer.querySelector('.znote-minimap-stage');
		var canvas = viewer.querySelector('.znote-minimap-canvas');
		var label  = viewer.querySelector('.znote-minimap-level b');
		if (!stage || !canvas) return;

		var TILE = 64;

		var index = 0;
		for (var i = 0; i < data.floors.length; i++) {
			if (data.floors[i].z === data.start) { index = i; break; }
		}

		var scale = 1, panX = 0, panY = 0;
		var dragging = false, startX = 0, startY = 0;
		var mounted = {};

		function floorData() { return data.floors[index]; }

		function focusPoint() {
			var floor = floorData();
			if (!floor.t.length) return { x: 0, y: 0 };

			var xs = [], ys = [];
			for (var i = 0; i < floor.t.length; i++) {
				xs.push(floor.t[i][0]);
				ys.push(floor.t[i][1]);
			}
			xs.sort(function (a, b) { return a - b; });
			ys.sort(function (a, b) { return a - b; });

			return {
				x: xs[Math.floor(xs.length / 2)] - floor.x + (TILE / 2),
				y: ys[Math.floor(ys.length / 2)] - floor.y + (TILE / 2)
			};
		}

		function center() {
			var point = focusPoint();
			panX = Math.round((stage.clientWidth / 2) - point.x * scale);
			panY = Math.round((stage.clientHeight / 2) - point.y * scale);
		}

		// Tiles are placed straight into stage space, one node per visible tile.
		// Panning a single huge element instead would ask the browser for a
		// layer far past its maximum texture size - a 32000px floor then paints
		// as nothing at all.
		function render() {
			var floor  = floorData();
			var width  = stage.clientWidth;
			var height = stage.clientHeight;
			var margin = TILE * 2;
			var seen   = {};

			for (var i = 0; i < floor.t.length; i++) {
				var fx = floor.t[i][0] - floor.x;
				var fy = floor.t[i][1] - floor.y;

				var left = Math.round(fx * scale + panX);
				var top  = Math.round(fy * scale + panY);
				var w    = Math.round((fx + TILE) * scale + panX) - left;
				var h    = Math.round((fy + TILE) * scale + panY) - top;

				if (left > width + margin || top > height + margin || left + w < -margin || top + h < -margin) {
					continue;
				}

				seen[i] = true;
				var img = mounted[i];

				if (!img) {
					img = document.createElement('img');
					img.alt = '';
					img.draggable = false;
					img.src = data.base + 'z' + floor.z + '-' + floor.t[i][0] + '-' + floor.t[i][1] + '.png?' + data.v;
					canvas.appendChild(img);
					mounted[i] = img;
				}

				img.style.left   = left + 'px';
				img.style.top    = top + 'px';
				img.style.width  = w + 'px';
				img.style.height = h + 'px';
			}

			for (var key in mounted) {
				if (!seen[key]) {
					canvas.removeChild(mounted[key]);
					delete mounted[key];
				}
			}
		}

		function clear() {
			canvas.textContent = '';
			mounted = {};
		}

		function showFloor(next) {
			index = (next + data.floors.length) % data.floors.length;
			if (label) label.textContent = floorData().z;
			clear();
			center();
			render();
		}

		function zoom(delta) {
			var next = Math.max(0.25, Math.min(4, scale + delta));
			if (next === scale) return;

			var cx = stage.clientWidth / 2, cy = stage.clientHeight / 2;
			panX = Math.round(cx - (cx - panX) * (next / scale));
			panY = Math.round(cy - (cy - panY) * (next / scale));
			scale = next;
			clear();
			render();
		}

		// Never capture the pointer for a press that started on a control:
		// capturing retargets the following pointerup, so the browser fires
		// the click on the stage instead of the button and it does nothing.
		stage.addEventListener('pointerdown', function (e) {
			if (e.target && e.target.closest && e.target.closest('[data-minimap]')) return;

			dragging = true;
			startX = e.clientX - panX;
			startY = e.clientY - panY;
			stage.setPointerCapture(e.pointerId);
			stage.classList.add('is-dragging');
		});
		stage.addEventListener('pointermove', function (e) {
			if (!dragging) return;
			panX = e.clientX - startX;
			panY = e.clientY - startY;
			render();
		});
		stage.addEventListener('pointerup', function (e) {
			dragging = false;
			stage.classList.remove('is-dragging');
			try { stage.releasePointerCapture(e.pointerId); } catch (err) {}
		});
		stage.addEventListener('wheel', function (e) {
			e.preventDefault();
			zoom(e.deltaY < 0 ? 0.25 : -0.25);
		}, { passive: false });

		viewer.addEventListener('click', function (e) {
			var button = e.target.closest('[data-minimap]');
			if (!button) return;

			var action = button.getAttribute('data-minimap');
			if (action === 'zoom-in') zoom(0.25);
			else if (action === 'zoom-out') zoom(-0.25);
			else if (action === 'floor-up') showFloor(index - 1);
			else if (action === 'floor-down') showFloor(index + 1);
		});

		window.addEventListener('resize', function () { center(); render(); });

		if (label) label.textContent = floorData().z;
		center();
		render();
		}

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', init);
		} else {
			init();
		}
	})();
	</script>
	<?php
}
