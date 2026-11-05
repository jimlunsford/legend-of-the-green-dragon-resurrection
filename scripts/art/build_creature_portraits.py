#!/usr/bin/env python3
"""Build the creature and master portraits from the parts kit (assets/portraits/creature/kit.json).

Every portrait is one SVG layered from parts: a frame tinted by creature type, wings, a body for the type, headgear,
ears, eyes, a mouth part and a held item, each part in the art palette. The kit names each creature's recipe; this
script only draws. Run: build_creature_portraits.py KIT_JSON OUT_DIR. The output is deterministic: the same kit gives
the same bytes, so a test can rebuild and compare. Art: Phoenix 2:33 LLC, CC BY-SA 4.0 (assets/LICENSE).
"""
import json
import pathlib
import sys
from xml.sax.saxutils import escape

DARK, SHADOW, FACE = '#0E0C0A', '#050403', '#1F1A14'
FRAME = '#24201A'
GOLD, MOON, PALE, EMBER, BRONZE, COPPER = '#D9A84E', '#E9D27C', '#E3CF9A', '#E8826A', '#C4936A', '#C08650'
WOOD, MAGIC, GREEN, LEAF, BONE, MUTED, ASH = '#6B563F', '#8FB8D9', '#9CCB8A', '#7FB069', '#ECE4D4', '#A99F8D', '#9A917F'
STONE, STONE2, HATC, BEARD = '#3A3329', '#2C2720', '#2A2219', '#2E2620'
COLORS = {'gold': GOLD, 'moon': MOON, 'pale': PALE, 'ember': EMBER, 'bronze': BRONZE, 'copper': COPPER, 'magic': MAGIC,
          'green': GREEN, 'leaf': LEAF, 'bone': BONE, 'muted': MUTED, 'ash': ASH}

# Per type: the frame's tint, the halo behind the head, the head's top and the eyes' height.
TYPES = {
    'humanoid': (BRONZE, '#2B241D', 82, 116), 'beast': (LEAF, '#232A20', 78, 114), 'undead': (BONE, '#24211C', 70, 106),
    'spirit': (MAGIC, '#252A2C', 66, 112), 'monster': (COPPER, '#2E2620', 72, 120), 'demon': (EMBER, '#2A2219', 74, 110),
    'construct': (ASH, '#2C2720', 78, 108), 'plant': (GREEN, '#1F2A1C', 68, 108), 'dragon': (GOLD, '#2A2620', 78, 104),
    'unknown': (MUTED, '#24211C', 66, 116),
}

ARCH = 'M10 240 L10 100 A90 90 0 0 1 190 100 L190 240'


def body(kind, tint):
    edge = f'stroke="{tint}" stroke-opacity="0.6" stroke-width="2"'
    if kind == 'humanoid':
        return (f'<path d="M30 240 C40 194 68 178 100 178 C132 178 160 194 170 240 Z" fill="{DARK}"/>'
                f'<rect x="91" y="146" width="18" height="36" fill="{DARK}"/>'
                f'<ellipse cx="100" cy="118" rx="27" ry="34" fill="{DARK}" {edge}/>')
    if kind == 'beast':
        return (f'<path d="M20 240 C30 200 60 186 100 186 C140 186 170 200 180 240 Z" fill="{DARK}"/>'
                f'<path d="M62 122 C62 92 80 78 100 78 C120 78 138 92 138 122 C138 148 122 166 100 166 C78 166 62 148 62 122 Z" fill="{DARK}" {edge}/>'
                f'<ellipse cx="100" cy="142" rx="18" ry="12" fill="{FACE}"/><ellipse cx="100" cy="136" rx="6" ry="4" fill="{tint}" opacity="0.8"/>')
    if kind == 'undead':
        return (f'<path d="M30 240 C40 196 68 182 100 182 C132 182 160 196 170 240 Z" fill="{DARK}"/>'
                f'<path d="M70 206 H130 M74 220 H126 M100 190 V240" stroke="{tint}" stroke-opacity="0.35" stroke-width="3"/>'
                f'<path d="M70 108 C70 82 84 70 100 70 C116 70 130 82 130 108 C130 124 124 132 118 136 L118 152 H82 L82 136 C76 132 70 124 70 108 Z" fill="{ASH}"/>'
                f'<path d="M90 140 V152 M100 140 V152 M110 140 V152" stroke="{DARK}" stroke-width="2.5"/><path d="M97 124 L100 118 L103 124 Z" fill="{DARK}"/>')
    if kind == 'spirit':
        return (f'<circle cx="100" cy="128" r="70" fill="{tint}" opacity="0.08"/>'
                f'<path d="M100 66 C130 80 140 118 132 148 C126 174 140 200 150 232 C130 222 118 238 100 224 C82 238 70 222 50 232 C60 200 74 174 68 148 C60 118 70 80 100 66 Z" fill="{DARK}" {edge}/>')
    if kind == 'monster':
        return (f'<path d="M8 240 C18 188 56 172 100 172 C144 172 182 188 192 240 Z" fill="{DARK}"/>'
                f'<path d="M56 126 C56 90 76 72 100 72 C124 72 144 90 144 126 C144 158 126 176 100 176 C74 176 56 158 56 126 Z" fill="{DARK}" {edge}/>'
                f'<path d="M64 108 Q100 92 136 108 L136 116 Q100 100 64 116 Z" fill="{FACE}"/>')
    if kind == 'demon':
        return (f'<path d="M28 240 C38 194 68 178 100 178 C132 178 162 194 172 240 Z" fill="{DARK}"/>'
                f'<path d="M74 112 C74 86 86 74 100 74 C114 74 126 86 126 112 C126 140 112 160 100 168 C88 160 74 140 74 112 Z" fill="{DARK}" {edge}/>')
    if kind == 'construct':
        return (f'<path d="M34 240 V198 H166 V240 Z" fill="{DARK}"/><rect x="90" y="156" width="20" height="44" fill="{DARK}"/>'
                f'<rect x="64" y="78" width="72" height="82" rx="6" fill="{DARK}" {edge}/>'
                f'<path d="M64 132 H136" stroke="{tint}" stroke-opacity="0.35" stroke-width="2"/>'
                + ''.join(f'<circle cx="{x}" cy="{y}" r="2.5" fill="{tint}" opacity="0.6"/>' for x in (72, 128) for y in (86, 152)))
    if kind == 'plant':
        return (f'<path d="M72 240 C80 200 78 170 84 140 L116 140 C122 170 120 200 128 240 Z" fill="{DARK}"/>'
                f'<path d="M84 196 C64 186 52 172 44 150 M116 186 C138 178 150 160 156 140" fill="none" stroke="{DARK}" stroke-width="7" stroke-linecap="round"/>'
                f'<path d="M60 118 C56 90 78 68 100 68 C122 68 144 90 140 118 C138 140 122 152 100 152 C78 152 62 140 60 118 Z" fill="{DARK}" {edge}/>'
                f'<path d="M86 100 C80 110 84 124 92 132 M116 96 C122 112 118 124 110 132" fill="none" stroke="{tint}" stroke-opacity="0.3" stroke-width="2"/>')
    if kind == 'dragon':
        return (f'<path d="M56 240 C66 202 88 182 94 150 L128 156 C126 190 140 212 152 240 Z" fill="{DARK}"/>'
                f'<path d="M64 112 C66 86 88 72 112 76 C136 80 158 96 166 118 C160 128 140 132 124 134 C110 152 84 152 72 138 C64 130 62 122 64 112 Z" fill="{DARK}" {edge}/>'
                f'<path d="M100 196 L106 182 L112 198 M116 214 L122 200 L128 216" fill="none" stroke="{tint}" stroke-opacity="0.4" stroke-width="2"/>'
                f'<circle cx="156" cy="116" r="2.5" fill="{tint}" opacity="0.8"/>')
    # unknown: a hooded traveller in shadow
    return (f'<path d="M20 240 C26 190 52 168 72 160 C62 140 60 108 70 88 C80 68 92 58 100 58 C108 58 120 68 130 88 C140 108 138 140 128 160 C148 168 174 190 180 240 Z" fill="{DARK}" {edge}/>'
            f'<path d="M80 150 C76 128 80 104 90 94 C96 88 104 88 110 94 C120 104 124 128 120 150 Z" fill="{SHADOW}"/>')


def wings(tint):
    one = 'M64 156 C34 126 16 86 22 52 C36 78 48 86 62 92 C52 72 52 58 56 42 C66 68 74 86 80 112 Z'
    return (f'<path d="{one}" fill="{FACE}" stroke="{tint}" stroke-opacity="0.5" stroke-width="2"/>'
            f'<path d="{one}" transform="translate(200 0) scale(-1 1)" fill="{FACE}" stroke="{tint}" stroke-opacity="0.5" stroke-width="2"/>')


def headgear(kind, top, tint):
    t = top
    if kind == 'crown':
        return f'<path d="M78 {t + 6} L82 {t - 12} L91 {t} L100 {t - 16} L109 {t} L118 {t - 12} L122 {t + 6} Z" fill="{GOLD}"/>'
    if kind == 'hood':
        return (f'<path d="M62 168 C56 108 72 {t - 10} 100 {t - 14} C128 {t - 10} 144 108 138 168 C126 150 116 146 100 146 C84 146 74 150 62 168 Z" fill="{HATC}" stroke="{tint}" stroke-opacity="0.4" stroke-width="2"/>'
                f'<ellipse cx="100" cy="{t + 38}" rx="20" ry="26" fill="{SHADOW}"/>')
    if kind == 'hat':
        return (f'<ellipse cx="100" cy="{t + 6}" rx="38" ry="7" fill="{HATC}"/><path d="M76 {t + 6} L106 {t - 50} L124 {t + 6} Z" fill="{HATC}"/>'
                f'<path d="M80 {t} H122" stroke="{tint}" stroke-width="3"/>')
    if kind == 'helmet':
        return (f'<path d="M70 {t + 32} C70 {t - 6} 130 {t - 6} 130 {t + 32} Z" fill="{STONE}"/><rect x="96" y="{t + 24}" width="8" height="22" fill="{STONE}"/>'
                f'<path d="M70 {t + 32} H130" stroke="{ASH}" stroke-width="3"/>')
    if kind == 'flame':
        return (f'<path d="M84 {t + 8} C80 {t - 12} 92 {t - 18} 94 {t - 34} C100 {t - 20} 104 {t - 26} 106 {t - 40} C114 {t - 24} 122 {t - 12} 116 {t + 8} Z" fill="{EMBER}"/>'
                f'<path d="M92 {t + 8} C90 {t - 4} 98 {t - 8} 100 {t - 20} C104 {t - 8} 110 {t - 4} 108 {t + 8} Z" fill="{GOLD}"/>')
    if kind == 'leaves':
        return ''.join(f'<ellipse cx="{x}" cy="{y}" rx="9" ry="5" fill="{LEAF}" opacity="0.8" transform="rotate({r} {x} {y})"/>'
                       for x, y, r in ((72, t + 8, -40), (86, t - 6, -20), (100, t - 10, 0), (114, t - 6, 20), (128, t + 8, 40), (140, t + 26, 60)))
    if kind == 'halo':
        return f'<ellipse cx="100" cy="{t - 10}" rx="26" ry="6" fill="none" stroke="{MOON}" stroke-width="3"/>'
    return ''


def horns(kind, top):
    t = top
    if kind == 'horns':
        one = f'M80 {t + 14} C66 {t - 4} 64 {t - 20} 72 {t - 34} C74 {t - 18} 82 {t - 6} 90 {t + 6} Z'
        return f'<path d="{one}" fill="{PALE}"/><path d="{one}" transform="translate(200 0) scale(-1 1)" fill="{PALE}"/>'
    if kind == 'unicorn':
        return f'<path d="M95 {t + 4} L100 {t - 42} L105 {t + 4} Z" fill="{PALE}"/><path d="M96 {t - 6} L104 {t - 10} M97 {t - 18} L103 {t - 22}" stroke="{GOLD}" stroke-width="2"/>'
    if kind == 'antlers':
        one = f'M84 {t + 6} C76 {t - 16} 70 {t - 24} 60 {t - 34} M76 {t - 12} L64 {t - 14} M72 {t - 22} L74 {t - 36}'
        return (f'<path d="{one}" fill="none" stroke="{PALE}" stroke-width="4" stroke-linecap="round"/>'
                f'<path d="{one}" transform="translate(200 0) scale(-1 1)" fill="none" stroke="{PALE}" stroke-width="4" stroke-linecap="round"/>')
    return ''


def ears(kind, top, tint):
    t = top
    edge = f'stroke="{tint}" stroke-opacity="0.6" stroke-width="2"'
    pairs = {
        'bat': f'M76 {t + 18} L58 {t - 28} L92 {t + 4} Z',
        'rabbit': f'M86 {t + 8} C78 {t - 40} 90 {t - 58} 96 {t - 8} Z',
        'cat': f'M70 {t + 18} L70 {t - 12} L90 {t + 4} Z',
        'pointed': f'M68 {t + 40} L42 {t + 18} L70 {t + 54} Z',
    }
    if kind == 'round':
        return ''.join(f'<circle cx="{x}" cy="{t + 6}" r="13" fill="{DARK}" {edge}/>' for x in (70, 130))
    if kind in pairs:
        d = pairs[kind]
        return f'<path d="{d}" fill="{DARK}" {edge}/><path d="{d}" transform="translate(200 0) scale(-1 1)" fill="{DARK}" {edge}/>'
    return ''


def eyes(style, y, color, kind):
    if kind == 'dragon':
        return f'<ellipse cx="108" cy="{y}" rx="7" ry="5" fill="{color}"/><ellipse cx="108" cy="{y}" rx="1.6" ry="4.5" fill="{SHADOW}"/>'
    if style == 'dots':
        return ''.join(f'<circle cx="{x}" cy="{y}" r="4" fill="{color}"/>' for x in (88, 112))
    if style == 'glow':
        return ''.join(f'<ellipse cx="{x}" cy="{y}" rx="5.5" ry="3" fill="{color}"/>' for x in (88, 112))
    if style == 'slits':
        return ''.join(f'<ellipse cx="{x}" cy="{y}" rx="5" ry="5" fill="{color}"/><ellipse cx="{x}" cy="{y}" rx="1.4" ry="4.6" fill="{SHADOW}"/>' for x in (88, 112))
    if style == 'cyclops':
        return f'<circle cx="100" cy="{y}" r="12" fill="{color}"/><circle cx="100" cy="{y}" r="4.5" fill="{SHADOW}"/>'
    if style == 'sockets':
        return ''.join(f'<circle cx="{x}" cy="{y}" r="9" fill="{SHADOW}"/><circle cx="{x}" cy="{y}" r="2.4" fill="{color}"/>' for x in (88, 112))
    if style == 'many':
        return ''.join(f'<circle cx="{x}" cy="{y + dy}" r="2.6" fill="{color}"/>' for x, dy in ((84, 0), (94, -4), (106, -4), (116, 0), (90, 8), (110, 8)))
    if style == 'square':
        return ''.join(f'<rect x="{x - 6}" y="{y - 4}" width="12" height="8" fill="{color}"/>' for x in (86, 114))
    return ''


def mouth(kind, y):
    if kind == 'tusks':
        return f'<path d="M82 {y + 34} L86 {y + 16} L91 {y + 34} Z M109 {y + 34} L114 {y + 16} L118 {y + 34} Z" fill="{BONE}"/>'
    if kind == 'fangs':
        return f'<path d="M90 {y + 20} L93 {y + 32} L96 {y + 20} Z M104 {y + 20} L107 {y + 32} L110 {y + 20} Z" fill="{BONE}"/>'
    if kind == 'beard':
        return f'<path d="M76 {y + 18} C78 {y + 58} 90 {y + 74} 100 {y + 82} C110 {y + 74} 122 {y + 58} 124 {y + 18} C112 {y + 30} 88 {y + 30} 76 {y + 18} Z" fill="{ASH}" opacity="0.85"/>'
    if kind == 'darkbeard':
        return f'<path d="M78 {y + 18} C80 {y + 52} 92 {y + 66} 100 {y + 72} C108 {y + 66} 120 {y + 52} 122 {y + 18} C112 {y + 28} 88 {y + 28} 78 {y + 18} Z" fill="{BEARD}"/>'
    if kind == 'grin':
        return f'<path d="M84 {y + 22} Q100 {y + 36} 116 {y + 22}" fill="none" stroke="{EMBER}" stroke-width="2.5" stroke-linecap="round"/>'
    return ''


HAIR = {'dark': '#2E2620', 'stone': STONE, 'grey': ASH, 'red': COPPER, 'brown': WOOD, 'blond': PALE}


def hair(style, color, top):
    c = HAIR[color]
    t = top
    if style == 'short':
        return f'<path d="M72 {t + 34} C70 {t + 4} 84 {t - 4} 100 {t - 4} C116 {t - 4} 130 {t + 4} 128 {t + 34} C120 {t + 18} 110 {t + 14} 100 {t + 14} C90 {t + 14} 80 {t + 18} 72 {t + 34} Z" fill="{c}"/>'
    if style == 'long':
        return (f'<path d="M70 {t + 30} C68 {t} 84 {t - 6} 100 {t - 6} C116 {t - 6} 132 {t} 130 {t + 30} L134 {t + 92} L122 {t + 84} L122 {t + 30} C112 {t + 16} 88 {t + 16} 78 {t + 30} L78 {t + 84} L66 {t + 92} Z" fill="{c}"/>')
    if style == 'bun':
        return (f'<circle cx="100" cy="{t - 10}" r="12" fill="{c}"/>'
                f'<path d="M72 {t + 30} C70 {t + 2} 84 {t - 4} 100 {t - 4} C116 {t - 4} 130 {t + 2} 128 {t + 30} C118 {t + 14} 82 {t + 14} 72 {t + 30} Z" fill="{c}"/>')
    if style == 'spiky':
        return f'<path d="M72 {t + 28} L74 {t - 4} L84 {t + 6} L90 {t - 12} L98 {t + 4} L106 {t - 14} L112 {t + 4} L120 {t - 8} L124 {t + 8} L128 {t + 28} C118 {t + 14} 82 {t + 14} 72 {t + 28} Z" fill="{c}"/>'
    return ''


def collar(color):
    if not color:
        return ''
    c = COLORS[color]
    return f'<path d="M66 196 L100 226 L134 196" fill="none" stroke="{c}" stroke-width="4" stroke-linejoin="round"/>'


def held(kind, tint):
    if kind == 'sword':
        return (f'<path d="M154 236 L166 124" stroke="{BONE}" stroke-width="6" stroke-linecap="round"/><path d="M146 206 L170 210" stroke="{GOLD}" stroke-width="5" stroke-linecap="round"/>'
                f'<path d="M156 212 L153 236" stroke="{WOOD}" stroke-width="6"/>')
    if kind == 'staff':
        return (f'<path d="M150 238 L162 96" stroke="{WOOD}" stroke-width="6" stroke-linecap="round"/><circle cx="163" cy="88" r="16" fill="{tint}" opacity="0.15"/>'
                f'<circle cx="163" cy="88" r="9" fill="{tint}"/>')
    if kind == 'bow':
        return f'<path d="M152 108 C182 140 182 200 152 232" fill="none" stroke="{WOOD}" stroke-width="5" stroke-linecap="round"/><path d="M152 108 L152 232" stroke="{BONE}" stroke-width="1.5"/>'
    if kind == 'club':
        return f'<path d="M150 238 L158 168 C152 150 162 132 172 138 C182 146 176 166 166 172 L158 238 Z" fill="{WOOD}"/><circle cx="168" cy="150" r="3" fill="{ASH}"/><circle cx="162" cy="160" r="3" fill="{ASH}"/>'
    if kind == 'dagger':
        return f'<path d="M150 214 L164 168" stroke="{BONE}" stroke-width="5" stroke-linecap="round"/><path d="M144 206 L160 212" stroke="{GOLD}" stroke-width="4" stroke-linecap="round"/>'
    if kind == 'scythe':
        return f'<path d="M150 238 L160 90" stroke="{WOOD}" stroke-width="5" stroke-linecap="round"/><path d="M160 92 C176 92 190 104 194 122 C182 108 172 104 160 104 Z" fill="{PALE}"/>'
    if kind == 'axe':
        return f'<path d="M150 238 L160 120" stroke="{WOOD}" stroke-width="5" stroke-linecap="round"/><path d="M158 126 C176 120 186 132 184 150 C176 142 166 142 156 146 Z" fill="{ASH}"/>'
    if kind == 'book':
        return f'<rect x="140" y="196" width="30" height="36" rx="2" fill="{COPPER}"/><path d="M144 200 V228" stroke="{DARK}" stroke-width="2"/>'
    if kind == 'mug':
        return f'<rect x="146" y="198" width="22" height="28" rx="3" fill="{BRONZE}"/><path d="M168 204 h7 v14 h-7" fill="none" stroke="{BRONZE}" stroke-width="3"/><rect x="146" y="198" width="22" height="6" fill="{PALE}"/>'
    return ''


def portrait(title, r):
    kind = r['type']
    tint, halo, top, eye_y = TYPES[kind]
    if r.get('tint'):
        tint = COLORS[r['tint']]
    eye_color = COLORS[r.get('eye_color', 'gold')]
    parts = [f'<path d="{ARCH} Z" fill="{FRAME}"/>', f'<circle cx="100" cy="112" r="62" fill="{halo}"/>']
    if r.get('wings'):
        parts.append(wings(tint))
    parts.append(horns(r.get('horns', ''), top))
    parts.append(ears(r.get('ears', ''), top, tint))
    if r.get('headgear') == 'hood':
        parts.append(body(kind, tint))
        parts.append(headgear('hood', top, tint))
    else:
        parts.append(body(kind, tint))
        parts.append(collar(r.get('collar', '')))
        parts.append(hair(r.get('hair', ''), r.get('hair_color', 'dark'), top))
        parts.append(headgear(r.get('headgear', ''), top, tint))
    eye_at = eye_y + (r.get('headgear') == 'hood') * 6
    parts.append(eyes(r.get('eyes', 'dots'), eye_at, eye_color, kind))
    parts.append(mouth(r.get('mouth', ''), eye_y))
    parts.append(held(r.get('held', ''), tint))
    parts.append(f'<path d="{ARCH}" fill="none" stroke="{tint}" stroke-width="3"/>')
    return ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 240">\n'
            f'<title>{escape(title)}</title>\n' + ''.join(p for p in parts if p) + '\n</svg>\n')


def main():
    kit = json.loads(pathlib.Path(sys.argv[1]).read_text())
    out = pathlib.Path(sys.argv[2])
    for group in ('creature', 'master', 'fallback', 'special'):
        for key, entry in kit[group].items():
            path = out / kit['paths'][group].format(key=key)
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text(portrait(entry['name'], entry['recipe']))


if __name__ == '__main__':
    main()
