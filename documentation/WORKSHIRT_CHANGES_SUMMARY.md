# Workshirt Page Changes Summary

**Tarikh:** 24 Disember 2025  
**Status:** Changes Applied & Saved

---

## 📝 Fail-fail yang Telah Diubah

### 1. **workshirt.html**
**Status:** ✅ Updated & Saved
- ✅ Full HTML structure dengan head & body
- ✅ Include `css/workshirt.css`
- ✅ Include `js/workshirt.js`
- ✅ Include Three.js & GSAP libraries (CDN)
- ✅ 3 slides dengan Projek Cuti Sem content
- ✅ Navigation structure
- ✅ Slider structure dengan class names untuk JS

**Location:** `workshirt.html`

---

### 2. **css/workshirt.css**
**Status:** ✅ Converted from SCSS & Saved
**Changes:**
- ✅ Converted semua SCSS nesting kepada plain CSS
- ✅ Converted SCSS `rgba(#fff, 0.5)` kepada `rgba(255, 255, 255, 0.5)`
- ✅ Expanded semua parent selector `&` kepada full selectors
- ✅ Converted BEM modifiers (`&--left`, `&__text`) kepada full class names
- ✅ Added styles untuk `<figure>` elements
- ✅ Logo positioning styles

**Location:** `css/workshirt.css`

---

### 3. **js/workshirt.js**
**Status:** ✅ Updated for Scroll Functionality & Saved

**Major Changes:**

#### a) GSAP API Update (v2 → v3)
- ✅ `TweenMax` → `gsap`
- ✅ `TimelineMax` → `gsap.timeline()`
- ✅ `alpha` → `opacity`
- ✅ Easing functions updated:
  - `Expo.easeInOut` → `"power2.inOut"`
  - `Power4.easeInOut` → `"power2.inOut"`
  - `Linear.easeNone` → `"none"`

#### b) Scroll Detection Improvements
- ✅ Wheel event listener dengan throttle (2.5s delay)
- ✅ Minimum scroll distance: 10px
- ✅ Touch swipe support untuk mobile
- ✅ Keyboard support (Arrow Down, Space, Page Down)
- ✅ Debug console logs

#### c) Texture Loading
- ✅ Async texture loading dengan callbacks
- ✅ Mesh creation setelah textures loaded
- ✅ Error handling untuk texture loading
- ✅ Fallback untuk displacement map

#### d) Initialization
- ✅ DOM ready check
- ✅ Element existence verification
- ✅ Window resize handler
- ✅ Better error handling

**Location:** `js/workshirt.js`

---

## 🔧 Dependencies

### Required Libraries (CDN):
1. **Three.js** (r128)
   ```html
   https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js
   ```

2. **GSAP v3**
   ```html
   https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js
   ```

---

## ✅ Testing Checklist

- [ ] Open `http://localhost/pcs.my/workshirt`
- [ ] Check browser console untuk errors
- [ ] Test scroll down (mouse wheel)
- [ ] Test touch swipe (mobile)
- [ ] Test keyboard (Arrow Down, Space)
- [ ] Verify slider transitions work
- [ ] Verify images load correctly
- [ ] Check responsive design

---

## 🐛 Known Issues / Notes

1. **Scroll Throttle:** Minimum 2.5 seconds between slides (matches animation duration)
2. **Texture Loading:** May take a moment on first load
3. **Displacement Map:** Uses external CDN URL (may need to host locally)

---

## 📍 File Locations

```
pcs.my/
├── workshirt.html          ← Updated
├── css/
│   └── workshirt.css       ← Converted from SCSS
└── js/
    └── workshirt.js        ← Updated for scroll
```

---

## 🚀 Next Steps

1. Test semua functionality
2. Optimize images jika perlu
3. Host displacement map locally (optional)
4. Add more slides jika perlu

---

**Semua perubahan telah disimpan dan siap digunakan!** ✅

