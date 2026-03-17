/**
* @vue/shared v3.5.30
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/
// @__NO_SIDE_EFFECTS__
function En(e) {
  const t = /* @__PURE__ */ Object.create(null);
  for (const s of e.split(",")) t[s] = 1;
  return (s) => s in t;
}
const ae = {}, Pt = [], Qe = () => {
}, Jr = () => !1, Ds = (e) => e.charCodeAt(0) === 111 && e.charCodeAt(1) === 110 && // uppercase letter
(e.charCodeAt(2) > 122 || e.charCodeAt(2) < 97), In = (e) => e.startsWith("onUpdate:"), ve = Object.assign, Hn = (e, t) => {
  const s = e.indexOf(t);
  s > -1 && e.splice(s, 1);
}, po = Object.prototype.hasOwnProperty, ie = (e, t) => po.call(e, t), q = Array.isArray, Lt = (e) => as(e) === "[object Map]", Rs = (e) => as(e) === "[object Set]", ir = (e) => as(e) === "[object Date]", X = (e) => typeof e == "function", pe = (e) => typeof e == "string", Xe = (e) => typeof e == "symbol", oe = (e) => e !== null && typeof e == "object", zr = (e) => (oe(e) || X(e)) && X(e.then) && X(e.catch), Zr = Object.prototype.toString, as = (e) => Zr.call(e), _o = (e) => as(e).slice(8, -1), Yr = (e) => as(e) === "[object Object]", Fn = (e) => pe(e) && e !== "NaN" && e[0] !== "-" && "" + parseInt(e, 10) === e, Jt = /* @__PURE__ */ En(
  // the leading comma is intentional so empty string "" is also included
  ",key,ref,ref_for,ref_key,onVnodeBeforeMount,onVnodeMounted,onVnodeBeforeUpdate,onVnodeUpdated,onVnodeBeforeUnmount,onVnodeUnmounted"
), js = (e) => {
  const t = /* @__PURE__ */ Object.create(null);
  return ((s) => t[s] || (t[s] = e(s)));
}, go = /-\w/g, Re = js(
  (e) => e.replace(go, (t) => t.slice(1).toUpperCase())
), mo = /\B([A-Z])/g, Tt = js(
  (e) => e.replace(mo, "-$1").toLowerCase()
), Qr = js((e) => e.charAt(0).toUpperCase() + e.slice(1)), Qs = js(
  (e) => e ? `on${Qr(e)}` : ""
), Ye = (e, t) => !Object.is(e, t), xs = (e, ...t) => {
  for (let s = 0; s < e.length; s++)
    e[s](...t);
}, Xr = (e, t, s, n = !1) => {
  Object.defineProperty(e, t, {
    configurable: !0,
    enumerable: !1,
    writable: n,
    value: s
  });
}, On = (e) => {
  const t = parseFloat(e);
  return isNaN(t) ? e : t;
}, vo = (e) => {
  const t = pe(e) ? Number(e) : NaN;
  return isNaN(t) ? e : t;
};
let or;
const Ns = () => or || (or = typeof globalThis < "u" ? globalThis : typeof self < "u" ? self : typeof window < "u" ? window : typeof global < "u" ? global : {});
function Dn(e) {
  if (q(e)) {
    const t = {};
    for (let s = 0; s < e.length; s++) {
      const n = e[s], r = pe(n) ? wo(n) : Dn(n);
      if (r)
        for (const i in r)
          t[i] = r[i];
    }
    return t;
  } else if (pe(e) || oe(e))
    return e;
}
const yo = /;(?![^(]*\))/g, xo = /:([^]+)/, bo = /\/\*[^]*?\*\//g;
function wo(e) {
  const t = {};
  return e.replace(bo, "").split(yo).forEach((s) => {
    if (s) {
      const n = s.split(xo);
      n.length > 1 && (t[n[0].trim()] = n[1].trim());
    }
  }), t;
}
function _e(e) {
  let t = "";
  if (pe(e))
    t = e;
  else if (q(e))
    for (let s = 0; s < e.length; s++) {
      const n = _e(e[s]);
      n && (t += n + " ");
    }
  else if (oe(e))
    for (const s in e)
      e[s] && (t += s + " ");
  return t.trim();
}
const ko = "itemscope,allowfullscreen,formnovalidate,ismap,nomodule,novalidate,readonly", Co = /* @__PURE__ */ En(ko);
function ei(e) {
  return !!e || e === "";
}
function $o(e, t) {
  if (e.length !== t.length) return !1;
  let s = !0;
  for (let n = 0; s && n < e.length; n++)
    s = us(e[n], t[n]);
  return s;
}
function us(e, t) {
  if (e === t) return !0;
  let s = ir(e), n = ir(t);
  if (s || n)
    return s && n ? e.getTime() === t.getTime() : !1;
  if (s = Xe(e), n = Xe(t), s || n)
    return e === t;
  if (s = q(e), n = q(t), s || n)
    return s && n ? $o(e, t) : !1;
  if (s = oe(e), n = oe(t), s || n) {
    if (!s || !n)
      return !1;
    const r = Object.keys(e).length, i = Object.keys(t).length;
    if (r !== i)
      return !1;
    for (const o in e) {
      const l = e.hasOwnProperty(o), c = t.hasOwnProperty(o);
      if (l && !c || !l && c || !us(e[o], t[o]))
        return !1;
    }
  }
  return String(e) === String(t);
}
function ti(e, t) {
  return e.findIndex((s) => us(s, t));
}
const si = (e) => !!(e && e.__v_isRef === !0), L = (e) => pe(e) ? e : e == null ? "" : q(e) || oe(e) && (e.toString === Zr || !X(e.toString)) ? si(e) ? L(e.value) : JSON.stringify(e, ni, 2) : String(e), ni = (e, t) => si(t) ? ni(e, t.value) : Lt(t) ? {
  [`Map(${t.size})`]: [...t.entries()].reduce(
    (s, [n, r], i) => (s[Xs(n, i) + " =>"] = r, s),
    {}
  )
} : Rs(t) ? {
  [`Set(${t.size})`]: [...t.values()].map((s) => Xs(s))
} : Xe(t) ? Xs(t) : oe(t) && !q(t) && !Yr(t) ? String(t) : t, Xs = (e, t = "") => {
  var s;
  return (
    // Symbol.description in es2019+ so we need to cast here to pass
    // the lib: es2016 check
    Xe(e) ? `Symbol(${(s = e.description) != null ? s : t})` : e
  );
};
/**
* @vue/reactivity v3.5.30
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/
let Pe;
class To {
  // TODO isolatedDeclarations "__v_skip"
  constructor(t = !1) {
    this.detached = t, this._active = !0, this._on = 0, this.effects = [], this.cleanups = [], this._isPaused = !1, this.__v_skip = !0, this.parent = Pe, !t && Pe && (this.index = (Pe.scopes || (Pe.scopes = [])).push(
      this
    ) - 1);
  }
  get active() {
    return this._active;
  }
  pause() {
    if (this._active) {
      this._isPaused = !0;
      let t, s;
      if (this.scopes)
        for (t = 0, s = this.scopes.length; t < s; t++)
          this.scopes[t].pause();
      for (t = 0, s = this.effects.length; t < s; t++)
        this.effects[t].pause();
    }
  }
  /**
   * Resumes the effect scope, including all child scopes and effects.
   */
  resume() {
    if (this._active && this._isPaused) {
      this._isPaused = !1;
      let t, s;
      if (this.scopes)
        for (t = 0, s = this.scopes.length; t < s; t++)
          this.scopes[t].resume();
      for (t = 0, s = this.effects.length; t < s; t++)
        this.effects[t].resume();
    }
  }
  run(t) {
    if (this._active) {
      const s = Pe;
      try {
        return Pe = this, t();
      } finally {
        Pe = s;
      }
    }
  }
  /**
   * This should only be called on non-detached scopes
   * @internal
   */
  on() {
    ++this._on === 1 && (this.prevScope = Pe, Pe = this);
  }
  /**
   * This should only be called on non-detached scopes
   * @internal
   */
  off() {
    this._on > 0 && --this._on === 0 && (Pe = this.prevScope, this.prevScope = void 0);
  }
  stop(t) {
    if (this._active) {
      this._active = !1;
      let s, n;
      for (s = 0, n = this.effects.length; s < n; s++)
        this.effects[s].stop();
      for (this.effects.length = 0, s = 0, n = this.cleanups.length; s < n; s++)
        this.cleanups[s]();
      if (this.cleanups.length = 0, this.scopes) {
        for (s = 0, n = this.scopes.length; s < n; s++)
          this.scopes[s].stop(!0);
        this.scopes.length = 0;
      }
      if (!this.detached && this.parent && !t) {
        const r = this.parent.scopes.pop();
        r && r !== this && (this.parent.scopes[this.index] = r, r.index = this.index);
      }
      this.parent = void 0;
    }
  }
}
function So() {
  return Pe;
}
let de;
const en = /* @__PURE__ */ new WeakSet();
class ri {
  constructor(t) {
    this.fn = t, this.deps = void 0, this.depsTail = void 0, this.flags = 5, this.next = void 0, this.cleanup = void 0, this.scheduler = void 0, Pe && Pe.active && Pe.effects.push(this);
  }
  pause() {
    this.flags |= 64;
  }
  resume() {
    this.flags & 64 && (this.flags &= -65, en.has(this) && (en.delete(this), this.trigger()));
  }
  /**
   * @internal
   */
  notify() {
    this.flags & 2 && !(this.flags & 32) || this.flags & 8 || oi(this);
  }
  run() {
    if (!(this.flags & 1))
      return this.fn();
    this.flags |= 2, lr(this), li(this);
    const t = de, s = je;
    de = this, je = !0;
    try {
      return this.fn();
    } finally {
      ci(this), de = t, je = s, this.flags &= -3;
    }
  }
  stop() {
    if (this.flags & 1) {
      for (let t = this.deps; t; t = t.nextDep)
        Nn(t);
      this.deps = this.depsTail = void 0, lr(this), this.onStop && this.onStop(), this.flags &= -2;
    }
  }
  trigger() {
    this.flags & 64 ? en.add(this) : this.scheduler ? this.scheduler() : this.runIfDirty();
  }
  /**
   * @internal
   */
  runIfDirty() {
    hn(this) && this.run();
  }
  get dirty() {
    return hn(this);
  }
}
let ii = 0, zt, Zt;
function oi(e, t = !1) {
  if (e.flags |= 8, t) {
    e.next = Zt, Zt = e;
    return;
  }
  e.next = zt, zt = e;
}
function Rn() {
  ii++;
}
function jn() {
  if (--ii > 0)
    return;
  if (Zt) {
    let t = Zt;
    for (Zt = void 0; t; ) {
      const s = t.next;
      t.next = void 0, t.flags &= -9, t = s;
    }
  }
  let e;
  for (; zt; ) {
    let t = zt;
    for (zt = void 0; t; ) {
      const s = t.next;
      if (t.next = void 0, t.flags &= -9, t.flags & 1)
        try {
          t.trigger();
        } catch (n) {
          e || (e = n);
        }
      t = s;
    }
  }
  if (e) throw e;
}
function li(e) {
  for (let t = e.deps; t; t = t.nextDep)
    t.version = -1, t.prevActiveLink = t.dep.activeLink, t.dep.activeLink = t;
}
function ci(e) {
  let t, s = e.depsTail, n = s;
  for (; n; ) {
    const r = n.prevDep;
    n.version === -1 ? (n === s && (s = r), Nn(n), Mo(n)) : t = n, n.dep.activeLink = n.prevActiveLink, n.prevActiveLink = void 0, n = r;
  }
  e.deps = t, e.depsTail = s;
}
function hn(e) {
  for (let t = e.deps; t; t = t.nextDep)
    if (t.dep.version !== t.version || t.dep.computed && (ai(t.dep.computed) || t.dep.version !== t.version))
      return !0;
  return !!e._dirty;
}
function ai(e) {
  if (e.flags & 4 && !(e.flags & 16) || (e.flags &= -17, e.globalVersion === ss) || (e.globalVersion = ss, !e.isSSR && e.flags & 128 && (!e.deps && !e._dirty || !hn(e))))
    return;
  e.flags |= 2;
  const t = e.dep, s = de, n = je;
  de = e, je = !0;
  try {
    li(e);
    const r = e.fn(e._value);
    (t.version === 0 || Ye(r, e._value)) && (e.flags |= 128, e._value = r, t.version++);
  } catch (r) {
    throw t.version++, r;
  } finally {
    de = s, je = n, ci(e), e.flags &= -3;
  }
}
function Nn(e, t = !1) {
  const { dep: s, prevSub: n, nextSub: r } = e;
  if (n && (n.nextSub = r, e.prevSub = void 0), r && (r.prevSub = n, e.nextSub = void 0), s.subs === e && (s.subs = n, !n && s.computed)) {
    s.computed.flags &= -5;
    for (let i = s.computed.deps; i; i = i.nextDep)
      Nn(i, !0);
  }
  !t && !--s.sc && s.map && s.map.delete(s.key);
}
function Mo(e) {
  const { prevDep: t, nextDep: s } = e;
  t && (t.nextDep = s, e.prevDep = void 0), s && (s.prevDep = t, e.nextDep = void 0);
}
let je = !0;
const ui = [];
function ct() {
  ui.push(je), je = !1;
}
function at() {
  const e = ui.pop();
  je = e === void 0 ? !0 : e;
}
function lr(e) {
  const { cleanup: t } = e;
  if (e.cleanup = void 0, t) {
    const s = de;
    de = void 0;
    try {
      t();
    } finally {
      de = s;
    }
  }
}
let ss = 0;
class Po {
  constructor(t, s) {
    this.sub = t, this.dep = s, this.version = s.version, this.nextDep = this.prevDep = this.nextSub = this.prevSub = this.prevActiveLink = void 0;
  }
}
class Vn {
  // TODO isolatedDeclarations "__v_skip"
  constructor(t) {
    this.computed = t, this.version = 0, this.activeLink = void 0, this.subs = void 0, this.map = void 0, this.key = void 0, this.sc = 0, this.__v_skip = !0;
  }
  track(t) {
    if (!de || !je || de === this.computed)
      return;
    let s = this.activeLink;
    if (s === void 0 || s.sub !== de)
      s = this.activeLink = new Po(de, this), de.deps ? (s.prevDep = de.depsTail, de.depsTail.nextDep = s, de.depsTail = s) : de.deps = de.depsTail = s, fi(s);
    else if (s.version === -1 && (s.version = this.version, s.nextDep)) {
      const n = s.nextDep;
      n.prevDep = s.prevDep, s.prevDep && (s.prevDep.nextDep = n), s.prevDep = de.depsTail, s.nextDep = void 0, de.depsTail.nextDep = s, de.depsTail = s, de.deps === s && (de.deps = n);
    }
    return s;
  }
  trigger(t) {
    this.version++, ss++, this.notify(t);
  }
  notify(t) {
    Rn();
    try {
      for (let s = this.subs; s; s = s.prevSub)
        s.sub.notify() && s.sub.dep.notify();
    } finally {
      jn();
    }
  }
}
function fi(e) {
  if (e.dep.sc++, e.sub.flags & 4) {
    const t = e.dep.computed;
    if (t && !e.dep.subs) {
      t.flags |= 20;
      for (let n = t.deps; n; n = n.nextDep)
        fi(n);
    }
    const s = e.dep.subs;
    s !== e && (e.prevSub = s, s && (s.nextSub = e)), e.dep.subs = e;
  }
}
const pn = /* @__PURE__ */ new WeakMap(), Ct = /* @__PURE__ */ Symbol(
  ""
), _n = /* @__PURE__ */ Symbol(
  ""
), ns = /* @__PURE__ */ Symbol(
  ""
);
function xe(e, t, s) {
  if (je && de) {
    let n = pn.get(e);
    n || pn.set(e, n = /* @__PURE__ */ new Map());
    let r = n.get(s);
    r || (n.set(s, r = new Vn()), r.map = n, r.key = s), r.track();
  }
}
function ot(e, t, s, n, r, i) {
  const o = pn.get(e);
  if (!o) {
    ss++;
    return;
  }
  const l = (c) => {
    c && c.trigger();
  };
  if (Rn(), t === "clear")
    o.forEach(l);
  else {
    const c = q(e), u = c && Fn(s);
    if (c && s === "length") {
      const a = Number(n);
      o.forEach((h, y) => {
        (y === "length" || y === ns || !Xe(y) && y >= a) && l(h);
      });
    } else
      switch ((s !== void 0 || o.has(void 0)) && l(o.get(s)), u && l(o.get(ns)), t) {
        case "add":
          c ? u && l(o.get("length")) : (l(o.get(Ct)), Lt(e) && l(o.get(_n)));
          break;
        case "delete":
          c || (l(o.get(Ct)), Lt(e) && l(o.get(_n)));
          break;
        case "set":
          Lt(e) && l(o.get(Ct));
          break;
      }
  }
  jn();
}
function St(e) {
  const t = /* @__PURE__ */ re(e);
  return t === e ? t : (xe(t, "iterate", ns), /* @__PURE__ */ Oe(e) ? t : t.map(Ne));
}
function Vs(e) {
  return xe(e = /* @__PURE__ */ re(e), "iterate", ns), e;
}
function ze(e, t) {
  return /* @__PURE__ */ ut(e) ? Ht(/* @__PURE__ */ $t(e) ? Ne(t) : t) : Ne(t);
}
const Lo = {
  __proto__: null,
  [Symbol.iterator]() {
    return tn(this, Symbol.iterator, (e) => ze(this, e));
  },
  concat(...e) {
    return St(this).concat(
      ...e.map((t) => q(t) ? St(t) : t)
    );
  },
  entries() {
    return tn(this, "entries", (e) => (e[1] = ze(this, e[1]), e));
  },
  every(e, t) {
    return et(this, "every", e, t, void 0, arguments);
  },
  filter(e, t) {
    return et(
      this,
      "filter",
      e,
      t,
      (s) => s.map((n) => ze(this, n)),
      arguments
    );
  },
  find(e, t) {
    return et(
      this,
      "find",
      e,
      t,
      (s) => ze(this, s),
      arguments
    );
  },
  findIndex(e, t) {
    return et(this, "findIndex", e, t, void 0, arguments);
  },
  findLast(e, t) {
    return et(
      this,
      "findLast",
      e,
      t,
      (s) => ze(this, s),
      arguments
    );
  },
  findLastIndex(e, t) {
    return et(this, "findLastIndex", e, t, void 0, arguments);
  },
  // flat, flatMap could benefit from ARRAY_ITERATE but are not straight-forward to implement
  forEach(e, t) {
    return et(this, "forEach", e, t, void 0, arguments);
  },
  includes(...e) {
    return sn(this, "includes", e);
  },
  indexOf(...e) {
    return sn(this, "indexOf", e);
  },
  join(e) {
    return St(this).join(e);
  },
  // keys() iterator only reads `length`, no optimization required
  lastIndexOf(...e) {
    return sn(this, "lastIndexOf", e);
  },
  map(e, t) {
    return et(this, "map", e, t, void 0, arguments);
  },
  pop() {
    return jt(this, "pop");
  },
  push(...e) {
    return jt(this, "push", e);
  },
  reduce(e, ...t) {
    return cr(this, "reduce", e, t);
  },
  reduceRight(e, ...t) {
    return cr(this, "reduceRight", e, t);
  },
  shift() {
    return jt(this, "shift");
  },
  // slice could use ARRAY_ITERATE but also seems to beg for range tracking
  some(e, t) {
    return et(this, "some", e, t, void 0, arguments);
  },
  splice(...e) {
    return jt(this, "splice", e);
  },
  toReversed() {
    return St(this).toReversed();
  },
  toSorted(e) {
    return St(this).toSorted(e);
  },
  toSpliced(...e) {
    return St(this).toSpliced(...e);
  },
  unshift(...e) {
    return jt(this, "unshift", e);
  },
  values() {
    return tn(this, "values", (e) => ze(this, e));
  }
};
function tn(e, t, s) {
  const n = Vs(e), r = n[t]();
  return n !== e && !/* @__PURE__ */ Oe(e) && (r._next = r.next, r.next = () => {
    const i = r._next();
    return i.done || (i.value = s(i.value)), i;
  }), r;
}
const Ao = Array.prototype;
function et(e, t, s, n, r, i) {
  const o = Vs(e), l = o !== e && !/* @__PURE__ */ Oe(e), c = o[t];
  if (c !== Ao[t]) {
    const h = c.apply(e, i);
    return l ? Ne(h) : h;
  }
  let u = s;
  o !== e && (l ? u = function(h, y) {
    return s.call(this, ze(e, h), y, e);
  } : s.length > 2 && (u = function(h, y) {
    return s.call(this, h, y, e);
  }));
  const a = c.call(o, u, n);
  return l && r ? r(a) : a;
}
function cr(e, t, s, n) {
  const r = Vs(e), i = r !== e && !/* @__PURE__ */ Oe(e);
  let o = s, l = !1;
  r !== e && (i ? (l = n.length === 0, o = function(u, a, h) {
    return l && (l = !1, u = ze(e, u)), s.call(this, u, ze(e, a), h, e);
  }) : s.length > 3 && (o = function(u, a, h) {
    return s.call(this, u, a, h, e);
  }));
  const c = r[t](o, ...n);
  return l ? ze(e, c) : c;
}
function sn(e, t, s) {
  const n = /* @__PURE__ */ re(e);
  xe(n, "iterate", ns);
  const r = n[t](...s);
  return (r === -1 || r === !1) && /* @__PURE__ */ Un(s[0]) ? (s[0] = /* @__PURE__ */ re(s[0]), n[t](...s)) : r;
}
function jt(e, t, s = []) {
  ct(), Rn();
  const n = (/* @__PURE__ */ re(e))[t].apply(e, s);
  return jn(), at(), n;
}
const Eo = /* @__PURE__ */ En("__proto__,__v_isRef,__isVue"), di = new Set(
  /* @__PURE__ */ Object.getOwnPropertyNames(Symbol).filter((e) => e !== "arguments" && e !== "caller").map((e) => Symbol[e]).filter(Xe)
);
function Io(e) {
  Xe(e) || (e = String(e));
  const t = /* @__PURE__ */ re(this);
  return xe(t, "has", e), t.hasOwnProperty(e);
}
class hi {
  constructor(t = !1, s = !1) {
    this._isReadonly = t, this._isShallow = s;
  }
  get(t, s, n) {
    if (s === "__v_skip") return t.__v_skip;
    const r = this._isReadonly, i = this._isShallow;
    if (s === "__v_isReactive")
      return !r;
    if (s === "__v_isReadonly")
      return r;
    if (s === "__v_isShallow")
      return i;
    if (s === "__v_raw")
      return n === (r ? i ? Bo : mi : i ? gi : _i).get(t) || // receiver is not the reactive proxy, but has the same prototype
      // this means the receiver is a user proxy of the reactive proxy
      Object.getPrototypeOf(t) === Object.getPrototypeOf(n) ? t : void 0;
    const o = q(t);
    if (!r) {
      let c;
      if (o && (c = Lo[s]))
        return c;
      if (s === "hasOwnProperty")
        return Io;
    }
    const l = Reflect.get(
      t,
      s,
      // if this is a proxy wrapping a ref, return methods using the raw ref
      // as receiver so that we don't have to call `toRaw` on the ref in all
      // its class methods
      /* @__PURE__ */ we(t) ? t : n
    );
    if ((Xe(s) ? di.has(s) : Eo(s)) || (r || xe(t, "get", s), i))
      return l;
    if (/* @__PURE__ */ we(l)) {
      const c = o && Fn(s) ? l : l.value;
      return r && oe(c) ? /* @__PURE__ */ mn(c) : c;
    }
    return oe(l) ? r ? /* @__PURE__ */ mn(l) : /* @__PURE__ */ Ws(l) : l;
  }
}
class pi extends hi {
  constructor(t = !1) {
    super(!1, t);
  }
  set(t, s, n, r) {
    let i = t[s];
    const o = q(t) && Fn(s);
    if (!this._isShallow) {
      const u = /* @__PURE__ */ ut(i);
      if (!/* @__PURE__ */ Oe(n) && !/* @__PURE__ */ ut(n) && (i = /* @__PURE__ */ re(i), n = /* @__PURE__ */ re(n)), !o && /* @__PURE__ */ we(i) && !/* @__PURE__ */ we(n))
        return u || (i.value = n), !0;
    }
    const l = o ? Number(s) < t.length : ie(t, s), c = Reflect.set(
      t,
      s,
      n,
      /* @__PURE__ */ we(t) ? t : r
    );
    return t === /* @__PURE__ */ re(r) && (l ? Ye(n, i) && ot(t, "set", s, n) : ot(t, "add", s, n)), c;
  }
  deleteProperty(t, s) {
    const n = ie(t, s);
    t[s];
    const r = Reflect.deleteProperty(t, s);
    return r && n && ot(t, "delete", s, void 0), r;
  }
  has(t, s) {
    const n = Reflect.has(t, s);
    return (!Xe(s) || !di.has(s)) && xe(t, "has", s), n;
  }
  ownKeys(t) {
    return xe(
      t,
      "iterate",
      q(t) ? "length" : Ct
    ), Reflect.ownKeys(t);
  }
}
class Ho extends hi {
  constructor(t = !1) {
    super(!0, t);
  }
  set(t, s) {
    return !0;
  }
  deleteProperty(t, s) {
    return !0;
  }
}
const Fo = /* @__PURE__ */ new pi(), Oo = /* @__PURE__ */ new Ho(), Do = /* @__PURE__ */ new pi(!0);
const gn = (e) => e, _s = (e) => Reflect.getPrototypeOf(e);
function Ro(e, t, s) {
  return function(...n) {
    const r = this.__v_raw, i = /* @__PURE__ */ re(r), o = Lt(i), l = e === "entries" || e === Symbol.iterator && o, c = e === "keys" && o, u = r[e](...n), a = s ? gn : t ? Ht : Ne;
    return !t && xe(
      i,
      "iterate",
      c ? _n : Ct
    ), ve(
      // inheriting all iterator properties
      Object.create(u),
      {
        // iterator protocol
        next() {
          const { value: h, done: y } = u.next();
          return y ? { value: h, done: y } : {
            value: l ? [a(h[0]), a(h[1])] : a(h),
            done: y
          };
        }
      }
    );
  };
}
function gs(e) {
  return function(...t) {
    return e === "delete" ? !1 : e === "clear" ? void 0 : this;
  };
}
function jo(e, t) {
  const s = {
    get(r) {
      const i = this.__v_raw, o = /* @__PURE__ */ re(i), l = /* @__PURE__ */ re(r);
      e || (Ye(r, l) && xe(o, "get", r), xe(o, "get", l));
      const { has: c } = _s(o), u = t ? gn : e ? Ht : Ne;
      if (c.call(o, r))
        return u(i.get(r));
      if (c.call(o, l))
        return u(i.get(l));
      i !== o && i.get(r);
    },
    get size() {
      const r = this.__v_raw;
      return !e && xe(/* @__PURE__ */ re(r), "iterate", Ct), r.size;
    },
    has(r) {
      const i = this.__v_raw, o = /* @__PURE__ */ re(i), l = /* @__PURE__ */ re(r);
      return e || (Ye(r, l) && xe(o, "has", r), xe(o, "has", l)), r === l ? i.has(r) : i.has(r) || i.has(l);
    },
    forEach(r, i) {
      const o = this, l = o.__v_raw, c = /* @__PURE__ */ re(l), u = t ? gn : e ? Ht : Ne;
      return !e && xe(c, "iterate", Ct), l.forEach((a, h) => r.call(i, u(a), u(h), o));
    }
  };
  return ve(
    s,
    e ? {
      add: gs("add"),
      set: gs("set"),
      delete: gs("delete"),
      clear: gs("clear")
    } : {
      add(r) {
        const i = /* @__PURE__ */ re(this), o = _s(i), l = /* @__PURE__ */ re(r), c = !t && !/* @__PURE__ */ Oe(r) && !/* @__PURE__ */ ut(r) ? l : r;
        return o.has.call(i, c) || Ye(r, c) && o.has.call(i, r) || Ye(l, c) && o.has.call(i, l) || (i.add(c), ot(i, "add", c, c)), this;
      },
      set(r, i) {
        !t && !/* @__PURE__ */ Oe(i) && !/* @__PURE__ */ ut(i) && (i = /* @__PURE__ */ re(i));
        const o = /* @__PURE__ */ re(this), { has: l, get: c } = _s(o);
        let u = l.call(o, r);
        u || (r = /* @__PURE__ */ re(r), u = l.call(o, r));
        const a = c.call(o, r);
        return o.set(r, i), u ? Ye(i, a) && ot(o, "set", r, i) : ot(o, "add", r, i), this;
      },
      delete(r) {
        const i = /* @__PURE__ */ re(this), { has: o, get: l } = _s(i);
        let c = o.call(i, r);
        c || (r = /* @__PURE__ */ re(r), c = o.call(i, r)), l && l.call(i, r);
        const u = i.delete(r);
        return c && ot(i, "delete", r, void 0), u;
      },
      clear() {
        const r = /* @__PURE__ */ re(this), i = r.size !== 0, o = r.clear();
        return i && ot(
          r,
          "clear",
          void 0,
          void 0
        ), o;
      }
    }
  ), [
    "keys",
    "values",
    "entries",
    Symbol.iterator
  ].forEach((r) => {
    s[r] = Ro(r, e, t);
  }), s;
}
function Wn(e, t) {
  const s = jo(e, t);
  return (n, r, i) => r === "__v_isReactive" ? !e : r === "__v_isReadonly" ? e : r === "__v_raw" ? n : Reflect.get(
    ie(s, r) && r in n ? s : n,
    r,
    i
  );
}
const No = {
  get: /* @__PURE__ */ Wn(!1, !1)
}, Vo = {
  get: /* @__PURE__ */ Wn(!1, !0)
}, Wo = {
  get: /* @__PURE__ */ Wn(!0, !1)
};
const _i = /* @__PURE__ */ new WeakMap(), gi = /* @__PURE__ */ new WeakMap(), mi = /* @__PURE__ */ new WeakMap(), Bo = /* @__PURE__ */ new WeakMap();
function Uo(e) {
  switch (e) {
    case "Object":
    case "Array":
      return 1;
    case "Map":
    case "Set":
    case "WeakMap":
    case "WeakSet":
      return 2;
    default:
      return 0;
  }
}
function Ko(e) {
  return e.__v_skip || !Object.isExtensible(e) ? 0 : Uo(_o(e));
}
// @__NO_SIDE_EFFECTS__
function Ws(e) {
  return /* @__PURE__ */ ut(e) ? e : Bn(
    e,
    !1,
    Fo,
    No,
    _i
  );
}
// @__NO_SIDE_EFFECTS__
function qo(e) {
  return Bn(
    e,
    !1,
    Do,
    Vo,
    gi
  );
}
// @__NO_SIDE_EFFECTS__
function mn(e) {
  return Bn(
    e,
    !0,
    Oo,
    Wo,
    mi
  );
}
function Bn(e, t, s, n, r) {
  if (!oe(e) || e.__v_raw && !(t && e.__v_isReactive))
    return e;
  const i = Ko(e);
  if (i === 0)
    return e;
  const o = r.get(e);
  if (o)
    return o;
  const l = new Proxy(
    e,
    i === 2 ? n : s
  );
  return r.set(e, l), l;
}
// @__NO_SIDE_EFFECTS__
function $t(e) {
  return /* @__PURE__ */ ut(e) ? /* @__PURE__ */ $t(e.__v_raw) : !!(e && e.__v_isReactive);
}
// @__NO_SIDE_EFFECTS__
function ut(e) {
  return !!(e && e.__v_isReadonly);
}
// @__NO_SIDE_EFFECTS__
function Oe(e) {
  return !!(e && e.__v_isShallow);
}
// @__NO_SIDE_EFFECTS__
function Un(e) {
  return e ? !!e.__v_raw : !1;
}
// @__NO_SIDE_EFFECTS__
function re(e) {
  const t = e && e.__v_raw;
  return t ? /* @__PURE__ */ re(t) : e;
}
function Go(e) {
  return !ie(e, "__v_skip") && Object.isExtensible(e) && Xr(e, "__v_skip", !0), e;
}
const Ne = (e) => oe(e) ? /* @__PURE__ */ Ws(e) : e, Ht = (e) => oe(e) ? /* @__PURE__ */ mn(e) : e;
// @__NO_SIDE_EFFECTS__
function we(e) {
  return e ? e.__v_isRef === !0 : !1;
}
// @__NO_SIDE_EFFECTS__
function He(e) {
  return Jo(e, !1);
}
function Jo(e, t) {
  return /* @__PURE__ */ we(e) ? e : new zo(e, t);
}
class zo {
  constructor(t, s) {
    this.dep = new Vn(), this.__v_isRef = !0, this.__v_isShallow = !1, this._rawValue = s ? t : /* @__PURE__ */ re(t), this._value = s ? t : Ne(t), this.__v_isShallow = s;
  }
  get value() {
    return this.dep.track(), this._value;
  }
  set value(t) {
    const s = this._rawValue, n = this.__v_isShallow || /* @__PURE__ */ Oe(t) || /* @__PURE__ */ ut(t);
    t = n ? t : /* @__PURE__ */ re(t), Ye(t, s) && (this._rawValue = t, this._value = n ? t : Ne(t), this.dep.trigger());
  }
}
function g(e) {
  return /* @__PURE__ */ we(e) ? e.value : e;
}
const Zo = {
  get: (e, t, s) => t === "__v_raw" ? e : g(Reflect.get(e, t, s)),
  set: (e, t, s, n) => {
    const r = e[t];
    return /* @__PURE__ */ we(r) && !/* @__PURE__ */ we(s) ? (r.value = s, !0) : Reflect.set(e, t, s, n);
  }
};
function vi(e) {
  return /* @__PURE__ */ $t(e) ? e : new Proxy(e, Zo);
}
class Yo {
  constructor(t, s, n) {
    this.fn = t, this.setter = s, this._value = void 0, this.dep = new Vn(this), this.__v_isRef = !0, this.deps = void 0, this.depsTail = void 0, this.flags = 16, this.globalVersion = ss - 1, this.next = void 0, this.effect = this, this.__v_isReadonly = !s, this.isSSR = n;
  }
  /**
   * @internal
   */
  notify() {
    if (this.flags |= 16, !(this.flags & 8) && // avoid infinite self recursion
    de !== this)
      return oi(this, !0), !0;
  }
  get value() {
    const t = this.dep.track();
    return ai(this), t && (t.version = this.dep.version), this._value;
  }
  set value(t) {
    this.setter && this.setter(t);
  }
}
// @__NO_SIDE_EFFECTS__
function Qo(e, t, s = !1) {
  let n, r;
  return X(e) ? n = e : (n = e.get, r = e.set), new Yo(n, r, s);
}
const ms = {}, $s = /* @__PURE__ */ new WeakMap();
let bt;
function Xo(e, t = !1, s = bt) {
  if (s) {
    let n = $s.get(s);
    n || $s.set(s, n = []), n.push(e);
  }
}
function el(e, t, s = ae) {
  const { immediate: n, deep: r, once: i, scheduler: o, augmentJob: l, call: c } = s, u = (T) => r ? T : /* @__PURE__ */ Oe(T) || r === !1 || r === 0 ? lt(T, 1) : lt(T);
  let a, h, y, $, R = !1, F = !1;
  if (/* @__PURE__ */ we(e) ? (h = () => e.value, R = /* @__PURE__ */ Oe(e)) : /* @__PURE__ */ $t(e) ? (h = () => u(e), R = !0) : q(e) ? (F = !0, R = e.some((T) => /* @__PURE__ */ $t(T) || /* @__PURE__ */ Oe(T)), h = () => e.map((T) => {
    if (/* @__PURE__ */ we(T))
      return T.value;
    if (/* @__PURE__ */ $t(T))
      return u(T);
    if (X(T))
      return c ? c(T, 2) : T();
  })) : X(e) ? t ? h = c ? () => c(e, 2) : e : h = () => {
    if (y) {
      ct();
      try {
        y();
      } finally {
        at();
      }
    }
    const T = bt;
    bt = a;
    try {
      return c ? c(e, 3, [$]) : e($);
    } finally {
      bt = T;
    }
  } : h = Qe, t && r) {
    const T = h, Y = r === !0 ? 1 / 0 : r;
    h = () => lt(T(), Y);
  }
  const Q = So(), B = () => {
    a.stop(), Q && Q.active && Hn(Q.effects, a);
  };
  if (i && t) {
    const T = t;
    t = (...Y) => {
      T(...Y), B();
    };
  }
  let I = F ? new Array(e.length).fill(ms) : ms;
  const S = (T) => {
    if (!(!(a.flags & 1) || !a.dirty && !T))
      if (t) {
        const Y = a.run();
        if (r || R || (F ? Y.some((J, se) => Ye(J, I[se])) : Ye(Y, I))) {
          y && y();
          const J = bt;
          bt = a;
          try {
            const se = [
              Y,
              // pass undefined as the old value when it's changed for the first time
              I === ms ? void 0 : F && I[0] === ms ? [] : I,
              $
            ];
            I = Y, c ? c(t, 3, se) : (
              // @ts-expect-error
              t(...se)
            );
          } finally {
            bt = J;
          }
        }
      } else
        a.run();
  };
  return l && l(S), a = new ri(h), a.scheduler = o ? () => o(S, !1) : S, $ = (T) => Xo(T, !1, a), y = a.onStop = () => {
    const T = $s.get(a);
    if (T) {
      if (c)
        c(T, 4);
      else
        for (const Y of T) Y();
      $s.delete(a);
    }
  }, t ? n ? S(!0) : I = a.run() : o ? o(S.bind(null, !0), !0) : a.run(), B.pause = a.pause.bind(a), B.resume = a.resume.bind(a), B.stop = B, B;
}
function lt(e, t = 1 / 0, s) {
  if (t <= 0 || !oe(e) || e.__v_skip || (s = s || /* @__PURE__ */ new Map(), (s.get(e) || 0) >= t))
    return e;
  if (s.set(e, t), t--, /* @__PURE__ */ we(e))
    lt(e.value, t, s);
  else if (q(e))
    for (let n = 0; n < e.length; n++)
      lt(e[n], t, s);
  else if (Rs(e) || Lt(e))
    e.forEach((n) => {
      lt(n, t, s);
    });
  else if (Yr(e)) {
    for (const n in e)
      lt(e[n], t, s);
    for (const n of Object.getOwnPropertySymbols(e))
      Object.prototype.propertyIsEnumerable.call(e, n) && lt(e[n], t, s);
  }
  return e;
}
/**
* @vue/runtime-core v3.5.30
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/
function fs(e, t, s, n) {
  try {
    return n ? e(...n) : e();
  } catch (r) {
    Bs(r, t, s);
  }
}
function Ve(e, t, s, n) {
  if (X(e)) {
    const r = fs(e, t, s, n);
    return r && zr(r) && r.catch((i) => {
      Bs(i, t, s);
    }), r;
  }
  if (q(e)) {
    const r = [];
    for (let i = 0; i < e.length; i++)
      r.push(Ve(e[i], t, s, n));
    return r;
  }
}
function Bs(e, t, s, n = !0) {
  const r = t ? t.vnode : null, { errorHandler: i, throwUnhandledErrorInProduction: o } = t && t.appContext.config || ae;
  if (t) {
    let l = t.parent;
    const c = t.proxy, u = `https://vuejs.org/error-reference/#runtime-${s}`;
    for (; l; ) {
      const a = l.ec;
      if (a) {
        for (let h = 0; h < a.length; h++)
          if (a[h](e, c, u) === !1)
            return;
      }
      l = l.parent;
    }
    if (i) {
      ct(), fs(i, null, 10, [
        e,
        c,
        u
      ]), at();
      return;
    }
  }
  tl(e, s, r, n, o);
}
function tl(e, t, s, n = !0, r = !1) {
  if (r)
    throw e;
  console.error(e);
}
const Ce = [];
let Ge = -1;
const At = [];
let ht = null, Mt = 0;
const yi = /* @__PURE__ */ Promise.resolve();
let Ts = null;
function sl(e) {
  const t = Ts || yi;
  return e ? t.then(this ? e.bind(this) : e) : t;
}
function nl(e) {
  let t = Ge + 1, s = Ce.length;
  for (; t < s; ) {
    const n = t + s >>> 1, r = Ce[n], i = rs(r);
    i < e || i === e && r.flags & 2 ? t = n + 1 : s = n;
  }
  return t;
}
function Kn(e) {
  if (!(e.flags & 1)) {
    const t = rs(e), s = Ce[Ce.length - 1];
    !s || // fast path when the job id is larger than the tail
    !(e.flags & 2) && t >= rs(s) ? Ce.push(e) : Ce.splice(nl(t), 0, e), e.flags |= 1, xi();
  }
}
function xi() {
  Ts || (Ts = yi.then(wi));
}
function rl(e) {
  q(e) ? At.push(...e) : ht && e.id === -1 ? ht.splice(Mt + 1, 0, e) : e.flags & 1 || (At.push(e), e.flags |= 1), xi();
}
function ar(e, t, s = Ge + 1) {
  for (; s < Ce.length; s++) {
    const n = Ce[s];
    if (n && n.flags & 2) {
      if (e && n.id !== e.uid)
        continue;
      Ce.splice(s, 1), s--, n.flags & 4 && (n.flags &= -2), n(), n.flags & 4 || (n.flags &= -2);
    }
  }
}
function bi(e) {
  if (At.length) {
    const t = [...new Set(At)].sort(
      (s, n) => rs(s) - rs(n)
    );
    if (At.length = 0, ht) {
      ht.push(...t);
      return;
    }
    for (ht = t, Mt = 0; Mt < ht.length; Mt++) {
      const s = ht[Mt];
      s.flags & 4 && (s.flags &= -2), s.flags & 8 || s(), s.flags &= -2;
    }
    ht = null, Mt = 0;
  }
}
const rs = (e) => e.id == null ? e.flags & 2 ? -1 : 1 / 0 : e.id;
function wi(e) {
  try {
    for (Ge = 0; Ge < Ce.length; Ge++) {
      const t = Ce[Ge];
      t && !(t.flags & 8) && (t.flags & 4 && (t.flags &= -2), fs(
        t,
        t.i,
        t.i ? 15 : 14
      ), t.flags & 4 || (t.flags &= -2));
    }
  } finally {
    for (; Ge < Ce.length; Ge++) {
      const t = Ce[Ge];
      t && (t.flags &= -2);
    }
    Ge = -1, Ce.length = 0, bi(), Ts = null, (Ce.length || At.length) && wi();
  }
}
let Fe = null, ki = null;
function Ss(e) {
  const t = Fe;
  return Fe = e, ki = e && e.type.__scopeId || null, t;
}
function Us(e, t = Fe, s) {
  if (!t || e._n)
    return e;
  const n = (...r) => {
    n._d && Ls(-1);
    const i = Ss(t);
    let o;
    try {
      o = e(...r);
    } finally {
      Ss(i), n._d && Ls(1);
    }
    return o;
  };
  return n._n = !0, n._c = !0, n._d = !0, n;
}
function qt(e, t) {
  if (Fe === null)
    return e;
  const s = Zs(Fe), n = e.dirs || (e.dirs = []);
  for (let r = 0; r < t.length; r++) {
    let [i, o, l, c = ae] = t[r];
    i && (X(i) && (i = {
      mounted: i,
      updated: i
    }), i.deep && lt(o), n.push({
      dir: i,
      instance: s,
      value: o,
      oldValue: void 0,
      arg: l,
      modifiers: c
    }));
  }
  return e;
}
function mt(e, t, s, n) {
  const r = e.dirs, i = t && t.dirs;
  for (let o = 0; o < r.length; o++) {
    const l = r[o];
    i && (l.oldValue = i[o].value);
    let c = l.dir[n];
    c && (ct(), Ve(c, s, 8, [
      e.el,
      l,
      e,
      t
    ]), at());
  }
}
function il(e, t) {
  if (Te) {
    let s = Te.provides;
    const n = Te.parent && Te.parent.provides;
    n === s && (s = Te.provides = Object.create(n)), s[e] = t;
  }
}
function bs(e, t, s = !1) {
  const n = to();
  if (n || Et) {
    let r = Et ? Et._context.provides : n ? n.parent == null || n.ce ? n.vnode.appContext && n.vnode.appContext.provides : n.parent.provides : void 0;
    if (r && e in r)
      return r[e];
    if (arguments.length > 1)
      return s && X(t) ? t.call(n && n.proxy) : t;
  }
}
const ol = /* @__PURE__ */ Symbol.for("v-scx"), ll = () => bs(ol);
function De(e, t, s) {
  return Ci(e, t, s);
}
function Ci(e, t, s = ae) {
  const { immediate: n, deep: r, flush: i, once: o } = s, l = ve({}, s), c = t && n || !t && i !== "post";
  let u;
  if (ls) {
    if (i === "sync") {
      const $ = ll();
      u = $.__watcherHandles || ($.__watcherHandles = []);
    } else if (!c) {
      const $ = () => {
      };
      return $.stop = Qe, $.resume = Qe, $.pause = Qe, $;
    }
  }
  const a = Te;
  l.call = ($, R, F) => Ve($, a, R, F);
  let h = !1;
  i === "post" ? l.scheduler = ($) => {
    ye($, a && a.suspense);
  } : i !== "sync" && (h = !0, l.scheduler = ($, R) => {
    R ? $() : Kn($);
  }), l.augmentJob = ($) => {
    t && ($.flags |= 4), h && ($.flags |= 2, a && ($.id = a.uid, $.i = a));
  };
  const y = el(e, t, l);
  return ls && (u ? u.push(y) : c && y()), y;
}
function cl(e, t, s) {
  const n = this.proxy, r = pe(e) ? e.includes(".") ? $i(n, e) : () => n[e] : e.bind(n, n);
  let i;
  X(t) ? i = t : (i = t.handler, s = t);
  const o = ds(this), l = Ci(r, i.bind(n), s);
  return o(), l;
}
function $i(e, t) {
  const s = t.split(".");
  return () => {
    let n = e;
    for (let r = 0; r < s.length && n; r++)
      n = n[s[r]];
    return n;
  };
}
const Ti = /* @__PURE__ */ Symbol("_vte"), Si = (e) => e.__isTeleport, Yt = (e) => e && (e.disabled || e.disabled === ""), ur = (e) => e && (e.defer || e.defer === ""), fr = (e) => typeof SVGElement < "u" && e instanceof SVGElement, dr = (e) => typeof MathMLElement == "function" && e instanceof MathMLElement, vn = (e, t) => {
  const s = e && e.to;
  return pe(s) ? t ? t(s) : null : s;
}, Mi = {
  name: "Teleport",
  __isTeleport: !0,
  process(e, t, s, n, r, i, o, l, c, u) {
    const {
      mc: a,
      pc: h,
      pbc: y,
      o: { insert: $, querySelector: R, createText: F, createComment: Q }
    } = u, B = Yt(t.props);
    let { shapeFlag: I, children: S, dynamicChildren: T } = t;
    if (e == null) {
      const Y = t.el = F(""), J = t.anchor = F("");
      $(Y, s, n), $(J, s, n);
      const se = (E, ee) => {
        I & 16 && a(
          S,
          E,
          ee,
          r,
          i,
          o,
          l,
          c
        );
      }, H = () => {
        const E = t.target = vn(t.props, R), ee = yn(E, t, F, $);
        E && (o !== "svg" && fr(E) ? o = "svg" : o !== "mathml" && dr(E) && (o = "mathml"), r && r.isCE && (r.ce._teleportTargets || (r.ce._teleportTargets = /* @__PURE__ */ new Set())).add(E), B || (se(E, ee), ws(t, !1)));
      };
      B && (se(s, J), ws(t, !0)), ur(t.props) ? (t.el.__isMounted = !1, ye(() => {
        H(), delete t.el.__isMounted;
      }, i)) : H();
    } else {
      if (ur(t.props) && e.el.__isMounted === !1) {
        ye(() => {
          Mi.process(
            e,
            t,
            s,
            n,
            r,
            i,
            o,
            l,
            c,
            u
          );
        }, i);
        return;
      }
      t.el = e.el, t.targetStart = e.targetStart;
      const Y = t.anchor = e.anchor, J = t.target = e.target, se = t.targetAnchor = e.targetAnchor, H = Yt(e.props), E = H ? s : J, ee = H ? Y : se;
      if (o === "svg" || fr(J) ? o = "svg" : (o === "mathml" || dr(J)) && (o = "mathml"), T ? (y(
        e.dynamicChildren,
        T,
        E,
        r,
        i,
        o,
        l
      ), Zn(e, t, !0)) : c || h(
        e,
        t,
        E,
        ee,
        r,
        i,
        o,
        l,
        !1
      ), B)
        H ? t.props && e.props && t.props.to !== e.props.to && (t.props.to = e.props.to) : vs(
          t,
          s,
          Y,
          u,
          1
        );
      else if ((t.props && t.props.to) !== (e.props && e.props.to)) {
        const ne = t.target = vn(
          t.props,
          R
        );
        ne && vs(
          t,
          ne,
          null,
          u,
          0
        );
      } else H && vs(
        t,
        J,
        se,
        u,
        1
      );
      ws(t, B);
    }
  },
  remove(e, t, s, { um: n, o: { remove: r } }, i) {
    const {
      shapeFlag: o,
      children: l,
      anchor: c,
      targetStart: u,
      targetAnchor: a,
      target: h,
      props: y
    } = e;
    if (h && (r(u), r(a)), i && r(c), o & 16) {
      const $ = i || !Yt(y);
      for (let R = 0; R < l.length; R++) {
        const F = l[R];
        n(
          F,
          t,
          s,
          $,
          !!F.dynamicChildren
        );
      }
    }
  },
  move: vs,
  hydrate: al
};
function vs(e, t, s, { o: { insert: n }, m: r }, i = 2) {
  i === 0 && n(e.targetAnchor, t, s);
  const { el: o, anchor: l, shapeFlag: c, children: u, props: a } = e, h = i === 2;
  if (h && n(o, t, s), (!h || Yt(a)) && c & 16)
    for (let y = 0; y < u.length; y++)
      r(
        u[y],
        t,
        s,
        2
      );
  h && n(l, t, s);
}
function al(e, t, s, n, r, i, {
  o: { nextSibling: o, parentNode: l, querySelector: c, insert: u, createText: a }
}, h) {
  function y(Q, B) {
    let I = B;
    for (; I; ) {
      if (I && I.nodeType === 8) {
        if (I.data === "teleport start anchor")
          t.targetStart = I;
        else if (I.data === "teleport anchor") {
          t.targetAnchor = I, Q._lpa = t.targetAnchor && o(t.targetAnchor);
          break;
        }
      }
      I = o(I);
    }
  }
  function $(Q, B) {
    B.anchor = h(
      o(Q),
      B,
      l(Q),
      s,
      n,
      r,
      i
    );
  }
  const R = t.target = vn(
    t.props,
    c
  ), F = Yt(t.props);
  if (R) {
    const Q = R._lpa || R.firstChild;
    t.shapeFlag & 16 && (F ? ($(e, t), y(R, Q), t.targetAnchor || yn(
      R,
      t,
      a,
      u,
      // if target is the same as the main view, insert anchors before current node
      // to avoid hydrating mismatch
      l(e) === R ? e : null
    )) : (t.anchor = o(e), y(R, Q), t.targetAnchor || yn(R, t, a, u), h(
      Q && o(Q),
      t,
      R,
      s,
      n,
      r,
      i
    ))), ws(t, F);
  } else F && t.shapeFlag & 16 && ($(e, t), t.targetStart = e, t.targetAnchor = o(e));
  return t.anchor && o(t.anchor);
}
const qn = Mi;
function ws(e, t) {
  const s = e.ctx;
  if (s && s.ut) {
    let n, r;
    for (t ? (n = e.el, r = e.anchor) : (n = e.targetStart, r = e.targetAnchor); n && n !== r; )
      n.nodeType === 1 && n.setAttribute("data-v-owner", s.uid), n = n.nextSibling;
    s.ut();
  }
}
function yn(e, t, s, n, r = null) {
  const i = t.targetStart = s(""), o = t.targetAnchor = s("");
  return i[Ti] = o, e && (n(i, e, r), n(o, e, r)), o;
}
const Je = /* @__PURE__ */ Symbol("_leaveCb"), Nt = /* @__PURE__ */ Symbol("_enterCb");
function ul() {
  const e = {
    isMounted: !1,
    isLeaving: !1,
    isUnmounting: !1,
    leavingVNodes: /* @__PURE__ */ new Map()
  };
  return Ft(() => {
    e.isMounted = !0;
  }), Oi(() => {
    e.isUnmounting = !0;
  }), e;
}
const Ie = [Function, Array], Pi = {
  mode: String,
  appear: Boolean,
  persisted: Boolean,
  // enter
  onBeforeEnter: Ie,
  onEnter: Ie,
  onAfterEnter: Ie,
  onEnterCancelled: Ie,
  // leave
  onBeforeLeave: Ie,
  onLeave: Ie,
  onAfterLeave: Ie,
  onLeaveCancelled: Ie,
  // appear
  onBeforeAppear: Ie,
  onAppear: Ie,
  onAfterAppear: Ie,
  onAppearCancelled: Ie
}, Li = (e) => {
  const t = e.subTree;
  return t.component ? Li(t.component) : t;
}, fl = {
  name: "BaseTransition",
  props: Pi,
  setup(e, { slots: t }) {
    const s = to(), n = ul();
    return () => {
      const r = t.default && Ii(t.default(), !0);
      if (!r || !r.length)
        return;
      const i = Ai(r), o = /* @__PURE__ */ re(e), { mode: l } = o;
      if (n.isLeaving)
        return nn(i);
      const c = hr(i);
      if (!c)
        return nn(i);
      let u = xn(
        c,
        o,
        n,
        s,
        // #11061, ensure enterHooks is fresh after clone
        (h) => u = h
      );
      c.type !== $e && is(c, u);
      let a = s.subTree && hr(s.subTree);
      if (a && a.type !== $e && !wt(a, c) && Li(s).type !== $e) {
        let h = xn(
          a,
          o,
          n,
          s
        );
        if (is(a, h), l === "out-in" && c.type !== $e)
          return n.isLeaving = !0, h.afterLeave = () => {
            n.isLeaving = !1, s.job.flags & 8 || s.update(), delete h.afterLeave, a = void 0;
          }, nn(i);
        l === "in-out" && c.type !== $e ? h.delayLeave = (y, $, R) => {
          const F = Ei(
            n,
            a
          );
          F[String(a.key)] = a, y[Je] = () => {
            $(), y[Je] = void 0, delete u.delayedLeave, a = void 0;
          }, u.delayedLeave = () => {
            R(), delete u.delayedLeave, a = void 0;
          };
        } : a = void 0;
      } else a && (a = void 0);
      return i;
    };
  }
};
function Ai(e) {
  let t = e[0];
  if (e.length > 1) {
    for (const s of e)
      if (s.type !== $e) {
        t = s;
        break;
      }
  }
  return t;
}
const dl = fl;
function Ei(e, t) {
  const { leavingVNodes: s } = e;
  let n = s.get(t.type);
  return n || (n = /* @__PURE__ */ Object.create(null), s.set(t.type, n)), n;
}
function xn(e, t, s, n, r) {
  const {
    appear: i,
    mode: o,
    persisted: l = !1,
    onBeforeEnter: c,
    onEnter: u,
    onAfterEnter: a,
    onEnterCancelled: h,
    onBeforeLeave: y,
    onLeave: $,
    onAfterLeave: R,
    onLeaveCancelled: F,
    onBeforeAppear: Q,
    onAppear: B,
    onAfterAppear: I,
    onAppearCancelled: S
  } = t, T = String(e.key), Y = Ei(s, e), J = (E, ee) => {
    E && Ve(
      E,
      n,
      9,
      ee
    );
  }, se = (E, ee) => {
    const ne = ee[1];
    J(E, ee), q(E) ? E.every((j) => j.length <= 1) && ne() : E.length <= 1 && ne();
  }, H = {
    mode: o,
    persisted: l,
    beforeEnter(E) {
      let ee = c;
      if (!s.isMounted)
        if (i)
          ee = Q || c;
        else
          return;
      E[Je] && E[Je](
        !0
        /* cancelled */
      );
      const ne = Y[T];
      ne && wt(e, ne) && ne.el[Je] && ne.el[Je](), J(ee, [E]);
    },
    enter(E) {
      if (Y[T] === e) return;
      let ee = u, ne = a, j = h;
      if (!s.isMounted)
        if (i)
          ee = B || u, ne = I || a, j = S || h;
        else
          return;
      let D = !1;
      E[Nt] = (x) => {
        D || (D = !0, x ? J(j, [E]) : J(ne, [E]), H.delayedLeave && H.delayedLeave(), E[Nt] = void 0);
      };
      const N = E[Nt].bind(null, !1);
      ee ? se(ee, [E, N]) : N();
    },
    leave(E, ee) {
      const ne = String(e.key);
      if (E[Nt] && E[Nt](
        !0
        /* cancelled */
      ), s.isUnmounting)
        return ee();
      J(y, [E]);
      let j = !1;
      E[Je] = (N) => {
        j || (j = !0, ee(), N ? J(F, [E]) : J(R, [E]), E[Je] = void 0, Y[ne] === e && delete Y[ne]);
      };
      const D = E[Je].bind(null, !1);
      Y[ne] = e, $ ? se($, [E, D]) : D();
    },
    clone(E) {
      const ee = xn(
        E,
        t,
        s,
        n,
        r
      );
      return r && r(ee), ee;
    }
  };
  return H;
}
function nn(e) {
  if (Ks(e))
    return e = pt(e), e.children = null, e;
}
function hr(e) {
  if (!Ks(e))
    return Si(e.type) && e.children ? Ai(e.children) : e;
  if (e.component)
    return e.component.subTree;
  const { shapeFlag: t, children: s } = e;
  if (s) {
    if (t & 16)
      return s[0];
    if (t & 32 && X(s.default))
      return s.default();
  }
}
function is(e, t) {
  e.shapeFlag & 6 && e.component ? (e.transition = t, is(e.component.subTree, t)) : e.shapeFlag & 128 ? (e.ssContent.transition = t.clone(e.ssContent), e.ssFallback.transition = t.clone(e.ssFallback)) : e.transition = t;
}
function Ii(e, t = !1, s) {
  let n = [], r = 0;
  for (let i = 0; i < e.length; i++) {
    let o = e[i];
    const l = s == null ? o.key : String(s) + String(o.key != null ? o.key : i);
    o.type === Z ? (o.patchFlag & 128 && r++, n = n.concat(
      Ii(o.children, t, l)
    )) : (t || o.type !== $e) && n.push(l != null ? pt(o, { key: l }) : o);
  }
  if (r > 1)
    for (let i = 0; i < n.length; i++)
      n[i].patchFlag = -2;
  return n;
}
function Hi(e) {
  e.ids = [e.ids[0] + e.ids[2]++ + "-", 0, 0];
}
function pr(e, t) {
  let s;
  return !!((s = Object.getOwnPropertyDescriptor(e, t)) && !s.configurable);
}
const Ms = /* @__PURE__ */ new WeakMap();
function Qt(e, t, s, n, r = !1) {
  if (q(e)) {
    e.forEach(
      (F, Q) => Qt(
        F,
        t && (q(t) ? t[Q] : t),
        s,
        n,
        r
      )
    );
    return;
  }
  if (Xt(n) && !r) {
    n.shapeFlag & 512 && n.type.__asyncResolved && n.component.subTree.component && Qt(e, t, s, n.component.subTree);
    return;
  }
  const i = n.shapeFlag & 4 ? Zs(n.component) : n.el, o = r ? null : i, { i: l, r: c } = e, u = t && t.r, a = l.refs === ae ? l.refs = {} : l.refs, h = l.setupState, y = /* @__PURE__ */ re(h), $ = h === ae ? Jr : (F) => pr(a, F) ? !1 : ie(y, F), R = (F, Q) => !(Q && pr(a, Q));
  if (u != null && u !== c) {
    if (_r(t), pe(u))
      a[u] = null, $(u) && (h[u] = null);
    else if (/* @__PURE__ */ we(u)) {
      const F = t;
      R(u, F.k) && (u.value = null), F.k && (a[F.k] = null);
    }
  }
  if (X(c))
    fs(c, l, 12, [o, a]);
  else {
    const F = pe(c), Q = /* @__PURE__ */ we(c);
    if (F || Q) {
      const B = () => {
        if (e.f) {
          const I = F ? $(c) ? h[c] : a[c] : R() || !e.k ? c.value : a[e.k];
          if (r)
            q(I) && Hn(I, i);
          else if (q(I))
            I.includes(i) || I.push(i);
          else if (F)
            a[c] = [i], $(c) && (h[c] = a[c]);
          else {
            const S = [i];
            R(c, e.k) && (c.value = S), e.k && (a[e.k] = S);
          }
        } else F ? (a[c] = o, $(c) && (h[c] = o)) : Q && (R(c, e.k) && (c.value = o), e.k && (a[e.k] = o));
      };
      if (o) {
        const I = () => {
          B(), Ms.delete(e);
        };
        I.id = -1, Ms.set(e, I), ye(I, s);
      } else
        _r(e), B();
    }
  }
}
function _r(e) {
  const t = Ms.get(e);
  t && (t.flags |= 8, Ms.delete(e));
}
Ns().requestIdleCallback;
Ns().cancelIdleCallback;
const Xt = (e) => !!e.type.__asyncLoader, Ks = (e) => e.type.__isKeepAlive;
function hl(e, t) {
  Fi(e, "a", t);
}
function pl(e, t) {
  Fi(e, "da", t);
}
function Fi(e, t, s = Te) {
  const n = e.__wdc || (e.__wdc = () => {
    let r = s;
    for (; r; ) {
      if (r.isDeactivated)
        return;
      r = r.parent;
    }
    return e();
  });
  if (qs(t, n, s), s) {
    let r = s.parent;
    for (; r && r.parent; )
      Ks(r.parent.vnode) && _l(n, t, s, r), r = r.parent;
  }
}
function _l(e, t, s, n) {
  const r = qs(
    t,
    e,
    n,
    !0
    /* prepend */
  );
  Gn(() => {
    Hn(n[t], r);
  }, s);
}
function qs(e, t, s = Te, n = !1) {
  if (s) {
    const r = s[e] || (s[e] = []), i = t.__weh || (t.__weh = (...o) => {
      ct();
      const l = ds(s), c = Ve(t, s, e, o);
      return l(), at(), c;
    });
    return n ? r.unshift(i) : r.push(i), i;
  }
}
const ft = (e) => (t, s = Te) => {
  (!ls || e === "sp") && qs(e, (...n) => t(...n), s);
}, gl = ft("bm"), Ft = ft("m"), ml = ft(
  "bu"
), vl = ft("u"), Oi = ft(
  "bum"
), Gn = ft("um"), yl = ft(
  "sp"
), xl = ft("rtg"), bl = ft("rtc");
function wl(e, t = Te) {
  qs("ec", e, t);
}
const kl = /* @__PURE__ */ Symbol.for("v-ndc");
function ce(e, t, s, n) {
  let r;
  const i = s, o = q(e);
  if (o || pe(e)) {
    const l = o && /* @__PURE__ */ $t(e);
    let c = !1, u = !1;
    l && (c = !/* @__PURE__ */ Oe(e), u = /* @__PURE__ */ ut(e), e = Vs(e)), r = new Array(e.length);
    for (let a = 0, h = e.length; a < h; a++)
      r[a] = t(
        c ? u ? Ht(Ne(e[a])) : Ne(e[a]) : e[a],
        a,
        void 0,
        i
      );
  } else if (typeof e == "number") {
    r = new Array(e);
    for (let l = 0; l < e; l++)
      r[l] = t(l + 1, l, void 0, i);
  } else if (oe(e))
    if (e[Symbol.iterator])
      r = Array.from(
        e,
        (l, c) => t(l, c, void 0, i)
      );
    else {
      const l = Object.keys(e);
      r = new Array(l.length);
      for (let c = 0, u = l.length; c < u; c++) {
        const a = l[c];
        r[c] = t(e[a], a, c, i);
      }
    }
  else
    r = [];
  return r;
}
const bn = (e) => e ? so(e) ? Zs(e) : bn(e.parent) : null, es = (
  // Move PURE marker to new line to workaround compiler discarding it
  // due to type annotation
  /* @__PURE__ */ ve(/* @__PURE__ */ Object.create(null), {
    $: (e) => e,
    $el: (e) => e.vnode.el,
    $data: (e) => e.data,
    $props: (e) => e.props,
    $attrs: (e) => e.attrs,
    $slots: (e) => e.slots,
    $refs: (e) => e.refs,
    $parent: (e) => bn(e.parent),
    $root: (e) => bn(e.root),
    $host: (e) => e.ce,
    $emit: (e) => e.emit,
    $options: (e) => Ri(e),
    $forceUpdate: (e) => e.f || (e.f = () => {
      Kn(e.update);
    }),
    $nextTick: (e) => e.n || (e.n = sl.bind(e.proxy)),
    $watch: (e) => cl.bind(e)
  })
), rn = (e, t) => e !== ae && !e.__isScriptSetup && ie(e, t), Cl = {
  get({ _: e }, t) {
    if (t === "__v_skip")
      return !0;
    const { ctx: s, setupState: n, data: r, props: i, accessCache: o, type: l, appContext: c } = e;
    if (t[0] !== "$") {
      const y = o[t];
      if (y !== void 0)
        switch (y) {
          case 1:
            return n[t];
          case 2:
            return r[t];
          case 4:
            return s[t];
          case 3:
            return i[t];
        }
      else {
        if (rn(n, t))
          return o[t] = 1, n[t];
        if (r !== ae && ie(r, t))
          return o[t] = 2, r[t];
        if (ie(i, t))
          return o[t] = 3, i[t];
        if (s !== ae && ie(s, t))
          return o[t] = 4, s[t];
        wn && (o[t] = 0);
      }
    }
    const u = es[t];
    let a, h;
    if (u)
      return t === "$attrs" && xe(e.attrs, "get", ""), u(e);
    if (
      // css module (injected by vue-loader)
      (a = l.__cssModules) && (a = a[t])
    )
      return a;
    if (s !== ae && ie(s, t))
      return o[t] = 4, s[t];
    if (
      // global properties
      h = c.config.globalProperties, ie(h, t)
    )
      return h[t];
  },
  set({ _: e }, t, s) {
    const { data: n, setupState: r, ctx: i } = e;
    return rn(r, t) ? (r[t] = s, !0) : n !== ae && ie(n, t) ? (n[t] = s, !0) : ie(e.props, t) || t[0] === "$" && t.slice(1) in e ? !1 : (i[t] = s, !0);
  },
  has({
    _: { data: e, setupState: t, accessCache: s, ctx: n, appContext: r, props: i, type: o }
  }, l) {
    let c;
    return !!(s[l] || e !== ae && l[0] !== "$" && ie(e, l) || rn(t, l) || ie(i, l) || ie(n, l) || ie(es, l) || ie(r.config.globalProperties, l) || (c = o.__cssModules) && c[l]);
  },
  defineProperty(e, t, s) {
    return s.get != null ? e._.accessCache[t] = 0 : ie(s, "value") && this.set(e, t, s.value, null), Reflect.defineProperty(e, t, s);
  }
};
function gr(e) {
  return q(e) ? e.reduce(
    (t, s) => (t[s] = null, t),
    {}
  ) : e;
}
let wn = !0;
function $l(e) {
  const t = Ri(e), s = e.proxy, n = e.ctx;
  wn = !1, t.beforeCreate && mr(t.beforeCreate, e, "bc");
  const {
    // state
    data: r,
    computed: i,
    methods: o,
    watch: l,
    provide: c,
    inject: u,
    // lifecycle
    created: a,
    beforeMount: h,
    mounted: y,
    beforeUpdate: $,
    updated: R,
    activated: F,
    deactivated: Q,
    beforeDestroy: B,
    beforeUnmount: I,
    destroyed: S,
    unmounted: T,
    render: Y,
    renderTracked: J,
    renderTriggered: se,
    errorCaptured: H,
    serverPrefetch: E,
    // public API
    expose: ee,
    inheritAttrs: ne,
    // assets
    components: j,
    directives: D,
    filters: N
  } = t;
  if (u && Tl(u, n, null), o)
    for (const he in o) {
      const ue = o[he];
      X(ue) && (n[he] = ue.bind(s));
    }
  if (r) {
    const he = r.call(s, s);
    oe(he) && (e.data = /* @__PURE__ */ Ws(he));
  }
  if (wn = !0, i)
    for (const he in i) {
      const ue = i[he], _t = X(ue) ? ue.bind(s, s) : X(ue.get) ? ue.get.bind(s, s) : Qe, hs = !X(ue) && X(ue.set) ? ue.set.bind(s) : Qe, gt = ge({
        get: _t,
        set: hs
      });
      Object.defineProperty(n, he, {
        enumerable: !0,
        configurable: !0,
        get: () => gt.value,
        set: (We) => gt.value = We
      });
    }
  if (l)
    for (const he in l)
      Di(l[he], n, s, he);
  if (c) {
    const he = X(c) ? c.call(s) : c;
    Reflect.ownKeys(he).forEach((ue) => {
      il(ue, he[ue]);
    });
  }
  a && mr(a, e, "c");
  function W(he, ue) {
    q(ue) ? ue.forEach((_t) => he(_t.bind(s))) : ue && he(ue.bind(s));
  }
  if (W(gl, h), W(Ft, y), W(ml, $), W(vl, R), W(hl, F), W(pl, Q), W(wl, H), W(bl, J), W(xl, se), W(Oi, I), W(Gn, T), W(yl, E), q(ee))
    if (ee.length) {
      const he = e.exposed || (e.exposed = {});
      ee.forEach((ue) => {
        Object.defineProperty(he, ue, {
          get: () => s[ue],
          set: (_t) => s[ue] = _t,
          enumerable: !0
        });
      });
    } else e.exposed || (e.exposed = {});
  Y && e.render === Qe && (e.render = Y), ne != null && (e.inheritAttrs = ne), j && (e.components = j), D && (e.directives = D), E && Hi(e);
}
function Tl(e, t, s = Qe) {
  q(e) && (e = kn(e));
  for (const n in e) {
    const r = e[n];
    let i;
    oe(r) ? "default" in r ? i = bs(
      r.from || n,
      r.default,
      !0
    ) : i = bs(r.from || n) : i = bs(r), /* @__PURE__ */ we(i) ? Object.defineProperty(t, n, {
      enumerable: !0,
      configurable: !0,
      get: () => i.value,
      set: (o) => i.value = o
    }) : t[n] = i;
  }
}
function mr(e, t, s) {
  Ve(
    q(e) ? e.map((n) => n.bind(t.proxy)) : e.bind(t.proxy),
    t,
    s
  );
}
function Di(e, t, s, n) {
  let r = n.includes(".") ? $i(s, n) : () => s[n];
  if (pe(e)) {
    const i = t[e];
    X(i) && De(r, i);
  } else if (X(e))
    De(r, e.bind(s));
  else if (oe(e))
    if (q(e))
      e.forEach((i) => Di(i, t, s, n));
    else {
      const i = X(e.handler) ? e.handler.bind(s) : t[e.handler];
      X(i) && De(r, i, e);
    }
}
function Ri(e) {
  const t = e.type, { mixins: s, extends: n } = t, {
    mixins: r,
    optionsCache: i,
    config: { optionMergeStrategies: o }
  } = e.appContext, l = i.get(t);
  let c;
  return l ? c = l : !r.length && !s && !n ? c = t : (c = {}, r.length && r.forEach(
    (u) => Ps(c, u, o, !0)
  ), Ps(c, t, o)), oe(t) && i.set(t, c), c;
}
function Ps(e, t, s, n = !1) {
  const { mixins: r, extends: i } = t;
  i && Ps(e, i, s, !0), r && r.forEach(
    (o) => Ps(e, o, s, !0)
  );
  for (const o in t)
    if (!(n && o === "expose")) {
      const l = Sl[o] || s && s[o];
      e[o] = l ? l(e[o], t[o]) : t[o];
    }
  return e;
}
const Sl = {
  data: vr,
  props: yr,
  emits: yr,
  // objects
  methods: Gt,
  computed: Gt,
  // lifecycle
  beforeCreate: ke,
  created: ke,
  beforeMount: ke,
  mounted: ke,
  beforeUpdate: ke,
  updated: ke,
  beforeDestroy: ke,
  beforeUnmount: ke,
  destroyed: ke,
  unmounted: ke,
  activated: ke,
  deactivated: ke,
  errorCaptured: ke,
  serverPrefetch: ke,
  // assets
  components: Gt,
  directives: Gt,
  // watch
  watch: Pl,
  // provide / inject
  provide: vr,
  inject: Ml
};
function vr(e, t) {
  return t ? e ? function() {
    return ve(
      X(e) ? e.call(this, this) : e,
      X(t) ? t.call(this, this) : t
    );
  } : t : e;
}
function Ml(e, t) {
  return Gt(kn(e), kn(t));
}
function kn(e) {
  if (q(e)) {
    const t = {};
    for (let s = 0; s < e.length; s++)
      t[e[s]] = e[s];
    return t;
  }
  return e;
}
function ke(e, t) {
  return e ? [...new Set([].concat(e, t))] : t;
}
function Gt(e, t) {
  return e ? ve(/* @__PURE__ */ Object.create(null), e, t) : t;
}
function yr(e, t) {
  return e ? q(e) && q(t) ? [.../* @__PURE__ */ new Set([...e, ...t])] : ve(
    /* @__PURE__ */ Object.create(null),
    gr(e),
    gr(t ?? {})
  ) : t;
}
function Pl(e, t) {
  if (!e) return t;
  if (!t) return e;
  const s = ve(/* @__PURE__ */ Object.create(null), e);
  for (const n in t)
    s[n] = ke(e[n], t[n]);
  return s;
}
function ji() {
  return {
    app: null,
    config: {
      isNativeTag: Jr,
      performance: !1,
      globalProperties: {},
      optionMergeStrategies: {},
      errorHandler: void 0,
      warnHandler: void 0,
      compilerOptions: {}
    },
    mixins: [],
    components: {},
    directives: {},
    provides: /* @__PURE__ */ Object.create(null),
    optionsCache: /* @__PURE__ */ new WeakMap(),
    propsCache: /* @__PURE__ */ new WeakMap(),
    emitsCache: /* @__PURE__ */ new WeakMap()
  };
}
let Ll = 0;
function Al(e, t) {
  return function(n, r = null) {
    X(n) || (n = ve({}, n)), r != null && !oe(r) && (r = null);
    const i = ji(), o = /* @__PURE__ */ new WeakSet(), l = [];
    let c = !1;
    const u = i.app = {
      _uid: Ll++,
      _component: n,
      _props: r,
      _container: null,
      _context: i,
      _instance: null,
      version: cc,
      get config() {
        return i.config;
      },
      set config(a) {
      },
      use(a, ...h) {
        return o.has(a) || (a && X(a.install) ? (o.add(a), a.install(u, ...h)) : X(a) && (o.add(a), a(u, ...h))), u;
      },
      mixin(a) {
        return i.mixins.includes(a) || i.mixins.push(a), u;
      },
      component(a, h) {
        return h ? (i.components[a] = h, u) : i.components[a];
      },
      directive(a, h) {
        return h ? (i.directives[a] = h, u) : i.directives[a];
      },
      mount(a, h, y) {
        if (!c) {
          const $ = u._ceVNode || be(n, r);
          return $.appContext = i, y === !0 ? y = "svg" : y === !1 && (y = void 0), e($, a, y), c = !0, u._container = a, a.__vue_app__ = u, Zs($.component);
        }
      },
      onUnmount(a) {
        l.push(a);
      },
      unmount() {
        c && (Ve(
          l,
          u._instance,
          16
        ), e(null, u._container), delete u._container.__vue_app__);
      },
      provide(a, h) {
        return i.provides[a] = h, u;
      },
      runWithContext(a) {
        const h = Et;
        Et = u;
        try {
          return a();
        } finally {
          Et = h;
        }
      }
    };
    return u;
  };
}
let Et = null;
const El = (e, t) => t === "modelValue" || t === "model-value" ? e.modelModifiers : e[`${t}Modifiers`] || e[`${Re(t)}Modifiers`] || e[`${Tt(t)}Modifiers`];
function Il(e, t, ...s) {
  if (e.isUnmounted) return;
  const n = e.vnode.props || ae;
  let r = s;
  const i = t.startsWith("update:"), o = i && El(n, t.slice(7));
  o && (o.trim && (r = s.map((a) => pe(a) ? a.trim() : a)), o.number && (r = s.map(On)));
  let l, c = n[l = Qs(t)] || // also try camelCase event handler (#2249)
  n[l = Qs(Re(t))];
  !c && i && (c = n[l = Qs(Tt(t))]), c && Ve(
    c,
    e,
    6,
    r
  );
  const u = n[l + "Once"];
  if (u) {
    if (!e.emitted)
      e.emitted = {};
    else if (e.emitted[l])
      return;
    e.emitted[l] = !0, Ve(
      u,
      e,
      6,
      r
    );
  }
}
const Hl = /* @__PURE__ */ new WeakMap();
function Ni(e, t, s = !1) {
  const n = s ? Hl : t.emitsCache, r = n.get(e);
  if (r !== void 0)
    return r;
  const i = e.emits;
  let o = {}, l = !1;
  if (!X(e)) {
    const c = (u) => {
      const a = Ni(u, t, !0);
      a && (l = !0, ve(o, a));
    };
    !s && t.mixins.length && t.mixins.forEach(c), e.extends && c(e.extends), e.mixins && e.mixins.forEach(c);
  }
  return !i && !l ? (oe(e) && n.set(e, null), null) : (q(i) ? i.forEach((c) => o[c] = null) : ve(o, i), oe(e) && n.set(e, o), o);
}
function Gs(e, t) {
  return !e || !Ds(t) ? !1 : (t = t.slice(2).replace(/Once$/, ""), ie(e, t[0].toLowerCase() + t.slice(1)) || ie(e, Tt(t)) || ie(e, t));
}
function xr(e) {
  const {
    type: t,
    vnode: s,
    proxy: n,
    withProxy: r,
    propsOptions: [i],
    slots: o,
    attrs: l,
    emit: c,
    render: u,
    renderCache: a,
    props: h,
    data: y,
    setupState: $,
    ctx: R,
    inheritAttrs: F
  } = e, Q = Ss(e);
  let B, I;
  try {
    if (s.shapeFlag & 4) {
      const T = r || n, Y = T;
      B = Ze(
        u.call(
          Y,
          T,
          a,
          h,
          $,
          y,
          R
        )
      ), I = l;
    } else {
      const T = t;
      B = Ze(
        T.length > 1 ? T(
          h,
          { attrs: l, slots: o, emit: c }
        ) : T(
          h,
          null
        )
      ), I = t.props ? l : Fl(l);
    }
  } catch (T) {
    ts.length = 0, Bs(T, e, 1), B = be($e);
  }
  let S = B;
  if (I && F !== !1) {
    const T = Object.keys(I), { shapeFlag: Y } = S;
    T.length && Y & 7 && (i && T.some(In) && (I = Ol(
      I,
      i
    )), S = pt(S, I, !1, !0));
  }
  return s.dirs && (S = pt(S, null, !1, !0), S.dirs = S.dirs ? S.dirs.concat(s.dirs) : s.dirs), s.transition && is(S, s.transition), B = S, Ss(Q), B;
}
const Fl = (e) => {
  let t;
  for (const s in e)
    (s === "class" || s === "style" || Ds(s)) && ((t || (t = {}))[s] = e[s]);
  return t;
}, Ol = (e, t) => {
  const s = {};
  for (const n in e)
    (!In(n) || !(n.slice(9) in t)) && (s[n] = e[n]);
  return s;
};
function Dl(e, t, s) {
  const { props: n, children: r, component: i } = e, { props: o, children: l, patchFlag: c } = t, u = i.emitsOptions;
  if (t.dirs || t.transition)
    return !0;
  if (s && c >= 0) {
    if (c & 1024)
      return !0;
    if (c & 16)
      return n ? br(n, o, u) : !!o;
    if (c & 8) {
      const a = t.dynamicProps;
      for (let h = 0; h < a.length; h++) {
        const y = a[h];
        if (Vi(o, n, y) && !Gs(u, y))
          return !0;
      }
    }
  } else
    return (r || l) && (!l || !l.$stable) ? !0 : n === o ? !1 : n ? o ? br(n, o, u) : !0 : !!o;
  return !1;
}
function br(e, t, s) {
  const n = Object.keys(t);
  if (n.length !== Object.keys(e).length)
    return !0;
  for (let r = 0; r < n.length; r++) {
    const i = n[r];
    if (Vi(t, e, i) && !Gs(s, i))
      return !0;
  }
  return !1;
}
function Vi(e, t, s) {
  const n = e[s], r = t[s];
  return s === "style" && oe(n) && oe(r) ? !us(n, r) : n !== r;
}
function Rl({ vnode: e, parent: t }, s) {
  for (; t; ) {
    const n = t.subTree;
    if (n.suspense && n.suspense.activeBranch === e && (n.el = e.el), n === e)
      (e = t.vnode).el = s, t = t.parent;
    else
      break;
  }
}
const Wi = {}, Bi = () => Object.create(Wi), Ui = (e) => Object.getPrototypeOf(e) === Wi;
function jl(e, t, s, n = !1) {
  const r = {}, i = Bi();
  e.propsDefaults = /* @__PURE__ */ Object.create(null), Ki(e, t, r, i);
  for (const o in e.propsOptions[0])
    o in r || (r[o] = void 0);
  s ? e.props = n ? r : /* @__PURE__ */ qo(r) : e.type.props ? e.props = r : e.props = i, e.attrs = i;
}
function Nl(e, t, s, n) {
  const {
    props: r,
    attrs: i,
    vnode: { patchFlag: o }
  } = e, l = /* @__PURE__ */ re(r), [c] = e.propsOptions;
  let u = !1;
  if (
    // always force full diff in dev
    // - #1942 if hmr is enabled with sfc component
    // - vite#872 non-sfc component used by sfc component
    (n || o > 0) && !(o & 16)
  ) {
    if (o & 8) {
      const a = e.vnode.dynamicProps;
      for (let h = 0; h < a.length; h++) {
        let y = a[h];
        if (Gs(e.emitsOptions, y))
          continue;
        const $ = t[y];
        if (c)
          if (ie(i, y))
            $ !== i[y] && (i[y] = $, u = !0);
          else {
            const R = Re(y);
            r[R] = Cn(
              c,
              l,
              R,
              $,
              e,
              !1
            );
          }
        else
          $ !== i[y] && (i[y] = $, u = !0);
      }
    }
  } else {
    Ki(e, t, r, i) && (u = !0);
    let a;
    for (const h in l)
      (!t || // for camelCase
      !ie(t, h) && // it's possible the original props was passed in as kebab-case
      // and converted to camelCase (#955)
      ((a = Tt(h)) === h || !ie(t, a))) && (c ? s && // for camelCase
      (s[h] !== void 0 || // for kebab-case
      s[a] !== void 0) && (r[h] = Cn(
        c,
        l,
        h,
        void 0,
        e,
        !0
      )) : delete r[h]);
    if (i !== l)
      for (const h in i)
        (!t || !ie(t, h)) && (delete i[h], u = !0);
  }
  u && ot(e.attrs, "set", "");
}
function Ki(e, t, s, n) {
  const [r, i] = e.propsOptions;
  let o = !1, l;
  if (t)
    for (let c in t) {
      if (Jt(c))
        continue;
      const u = t[c];
      let a;
      r && ie(r, a = Re(c)) ? !i || !i.includes(a) ? s[a] = u : (l || (l = {}))[a] = u : Gs(e.emitsOptions, c) || (!(c in n) || u !== n[c]) && (n[c] = u, o = !0);
    }
  if (i) {
    const c = /* @__PURE__ */ re(s), u = l || ae;
    for (let a = 0; a < i.length; a++) {
      const h = i[a];
      s[h] = Cn(
        r,
        c,
        h,
        u[h],
        e,
        !ie(u, h)
      );
    }
  }
  return o;
}
function Cn(e, t, s, n, r, i) {
  const o = e[s];
  if (o != null) {
    const l = ie(o, "default");
    if (l && n === void 0) {
      const c = o.default;
      if (o.type !== Function && !o.skipFactory && X(c)) {
        const { propsDefaults: u } = r;
        if (s in u)
          n = u[s];
        else {
          const a = ds(r);
          n = u[s] = c.call(
            null,
            t
          ), a();
        }
      } else
        n = c;
      r.ce && r.ce._setProp(s, n);
    }
    o[
      0
      /* shouldCast */
    ] && (i && !l ? n = !1 : o[
      1
      /* shouldCastTrue */
    ] && (n === "" || n === Tt(s)) && (n = !0));
  }
  return n;
}
const Vl = /* @__PURE__ */ new WeakMap();
function qi(e, t, s = !1) {
  const n = s ? Vl : t.propsCache, r = n.get(e);
  if (r)
    return r;
  const i = e.props, o = {}, l = [];
  let c = !1;
  if (!X(e)) {
    const a = (h) => {
      c = !0;
      const [y, $] = qi(h, t, !0);
      ve(o, y), $ && l.push(...$);
    };
    !s && t.mixins.length && t.mixins.forEach(a), e.extends && a(e.extends), e.mixins && e.mixins.forEach(a);
  }
  if (!i && !c)
    return oe(e) && n.set(e, Pt), Pt;
  if (q(i))
    for (let a = 0; a < i.length; a++) {
      const h = Re(i[a]);
      wr(h) && (o[h] = ae);
    }
  else if (i)
    for (const a in i) {
      const h = Re(a);
      if (wr(h)) {
        const y = i[a], $ = o[h] = q(y) || X(y) ? { type: y } : ve({}, y), R = $.type;
        let F = !1, Q = !0;
        if (q(R))
          for (let B = 0; B < R.length; ++B) {
            const I = R[B], S = X(I) && I.name;
            if (S === "Boolean") {
              F = !0;
              break;
            } else S === "String" && (Q = !1);
          }
        else
          F = X(R) && R.name === "Boolean";
        $[
          0
          /* shouldCast */
        ] = F, $[
          1
          /* shouldCastTrue */
        ] = Q, (F || ie($, "default")) && l.push(h);
      }
    }
  const u = [o, l];
  return oe(e) && n.set(e, u), u;
}
function wr(e) {
  return e[0] !== "$" && !Jt(e);
}
const Jn = (e) => e === "_" || e === "_ctx" || e === "$stable", zn = (e) => q(e) ? e.map(Ze) : [Ze(e)], Wl = (e, t, s) => {
  if (t._n)
    return t;
  const n = Us((...r) => zn(t(...r)), s);
  return n._c = !1, n;
}, Gi = (e, t, s) => {
  const n = e._ctx;
  for (const r in e) {
    if (Jn(r)) continue;
    const i = e[r];
    if (X(i))
      t[r] = Wl(r, i, n);
    else if (i != null) {
      const o = zn(i);
      t[r] = () => o;
    }
  }
}, Ji = (e, t) => {
  const s = zn(t);
  e.slots.default = () => s;
}, zi = (e, t, s) => {
  for (const n in t)
    (s || !Jn(n)) && (e[n] = t[n]);
}, Bl = (e, t, s) => {
  const n = e.slots = Bi();
  if (e.vnode.shapeFlag & 32) {
    const r = t._;
    r ? (zi(n, t, s), s && Xr(n, "_", r, !0)) : Gi(t, n);
  } else t && Ji(e, t);
}, Ul = (e, t, s) => {
  const { vnode: n, slots: r } = e;
  let i = !0, o = ae;
  if (n.shapeFlag & 32) {
    const l = t._;
    l ? s && l === 1 ? i = !1 : zi(r, t, s) : (i = !t.$stable, Gi(t, r)), o = t;
  } else t && (Ji(e, t), o = { default: 1 });
  if (i)
    for (const l in r)
      !Jn(l) && o[l] == null && delete r[l];
}, ye = zl;
function Kl(e) {
  return ql(e);
}
function ql(e, t) {
  const s = Ns();
  s.__VUE__ = !0;
  const {
    insert: n,
    remove: r,
    patchProp: i,
    createElement: o,
    createText: l,
    createComment: c,
    setText: u,
    setElementText: a,
    parentNode: h,
    nextSibling: y,
    setScopeId: $ = Qe,
    insertStaticContent: R
  } = e, F = (f, d, v, C = null, b = null, w = null, A = void 0, P = null, M = !!d.dynamicChildren) => {
    if (f === d)
      return;
    f && !wt(f, d) && (C = ps(f), We(f, b, w, !0), f = null), d.patchFlag === -2 && (M = !1, d.dynamicChildren = null);
    const { type: k, ref: G, shapeFlag: O } = d;
    switch (k) {
      case Js:
        Q(f, d, v, C);
        break;
      case $e:
        B(f, d, v, C);
        break;
      case ln:
        f == null && I(d, v, C, A);
        break;
      case Z:
        j(
          f,
          d,
          v,
          C,
          b,
          w,
          A,
          P,
          M
        );
        break;
      default:
        O & 1 ? Y(
          f,
          d,
          v,
          C,
          b,
          w,
          A,
          P,
          M
        ) : O & 6 ? D(
          f,
          d,
          v,
          C,
          b,
          w,
          A,
          P,
          M
        ) : (O & 64 || O & 128) && k.process(
          f,
          d,
          v,
          C,
          b,
          w,
          A,
          P,
          M,
          Dt
        );
    }
    G != null && b ? Qt(G, f && f.ref, w, d || f, !d) : G == null && f && f.ref != null && Qt(f.ref, null, w, f, !0);
  }, Q = (f, d, v, C) => {
    if (f == null)
      n(
        d.el = l(d.children),
        v,
        C
      );
    else {
      const b = d.el = f.el;
      d.children !== f.children && u(b, d.children);
    }
  }, B = (f, d, v, C) => {
    f == null ? n(
      d.el = c(d.children || ""),
      v,
      C
    ) : d.el = f.el;
  }, I = (f, d, v, C) => {
    [f.el, f.anchor] = R(
      f.children,
      d,
      v,
      C,
      f.el,
      f.anchor
    );
  }, S = ({ el: f, anchor: d }, v, C) => {
    let b;
    for (; f && f !== d; )
      b = y(f), n(f, v, C), f = b;
    n(d, v, C);
  }, T = ({ el: f, anchor: d }) => {
    let v;
    for (; f && f !== d; )
      v = y(f), r(f), f = v;
    r(d);
  }, Y = (f, d, v, C, b, w, A, P, M) => {
    if (d.type === "svg" ? A = "svg" : d.type === "math" && (A = "mathml"), f == null)
      J(
        d,
        v,
        C,
        b,
        w,
        A,
        P,
        M
      );
    else {
      const k = f.el && f.el._isVueCE ? f.el : null;
      try {
        k && k._beginPatch(), E(
          f,
          d,
          b,
          w,
          A,
          P,
          M
        );
      } finally {
        k && k._endPatch();
      }
    }
  }, J = (f, d, v, C, b, w, A, P) => {
    let M, k;
    const { props: G, shapeFlag: O, transition: K, dirs: z } = f;
    if (M = f.el = o(
      f.type,
      w,
      G && G.is,
      G
    ), O & 8 ? a(M, f.children) : O & 16 && H(
      f.children,
      M,
      null,
      C,
      b,
      on(f, w),
      A,
      P
    ), z && mt(f, null, C, "created"), se(M, f, f.scopeId, A, C), G) {
      for (const fe in G)
        fe !== "value" && !Jt(fe) && i(M, fe, null, G[fe], w, C);
      "value" in G && i(M, "value", null, G.value, w), (k = G.onVnodeBeforeMount) && qe(k, C, f);
    }
    z && mt(f, null, C, "beforeMount");
    const te = Gl(b, K);
    te && K.beforeEnter(M), n(M, d, v), ((k = G && G.onVnodeMounted) || te || z) && ye(() => {
      k && qe(k, C, f), te && K.enter(M), z && mt(f, null, C, "mounted");
    }, b);
  }, se = (f, d, v, C, b) => {
    if (v && $(f, v), C)
      for (let w = 0; w < C.length; w++)
        $(f, C[w]);
    if (b) {
      let w = b.subTree;
      if (d === w || Qi(w.type) && (w.ssContent === d || w.ssFallback === d)) {
        const A = b.vnode;
        se(
          f,
          A,
          A.scopeId,
          A.slotScopeIds,
          b.parent
        );
      }
    }
  }, H = (f, d, v, C, b, w, A, P, M = 0) => {
    for (let k = M; k < f.length; k++) {
      const G = f[k] = P ? it(f[k]) : Ze(f[k]);
      F(
        null,
        G,
        d,
        v,
        C,
        b,
        w,
        A,
        P
      );
    }
  }, E = (f, d, v, C, b, w, A) => {
    const P = d.el = f.el;
    let { patchFlag: M, dynamicChildren: k, dirs: G } = d;
    M |= f.patchFlag & 16;
    const O = f.props || ae, K = d.props || ae;
    let z;
    if (v && vt(v, !1), (z = K.onVnodeBeforeUpdate) && qe(z, v, d, f), G && mt(d, f, v, "beforeUpdate"), v && vt(v, !0), (O.innerHTML && K.innerHTML == null || O.textContent && K.textContent == null) && a(P, ""), k ? ee(
      f.dynamicChildren,
      k,
      P,
      v,
      C,
      on(d, b),
      w
    ) : A || ue(
      f,
      d,
      P,
      null,
      v,
      C,
      on(d, b),
      w,
      !1
    ), M > 0) {
      if (M & 16)
        ne(P, O, K, v, b);
      else if (M & 2 && O.class !== K.class && i(P, "class", null, K.class, b), M & 4 && i(P, "style", O.style, K.style, b), M & 8) {
        const te = d.dynamicProps;
        for (let fe = 0; fe < te.length; fe++) {
          const le = te[fe], Se = O[le], Me = K[le];
          (Me !== Se || le === "value") && i(P, le, Se, Me, b, v);
        }
      }
      M & 1 && f.children !== d.children && a(P, d.children);
    } else !A && k == null && ne(P, O, K, v, b);
    ((z = K.onVnodeUpdated) || G) && ye(() => {
      z && qe(z, v, d, f), G && mt(d, f, v, "updated");
    }, C);
  }, ee = (f, d, v, C, b, w, A) => {
    for (let P = 0; P < d.length; P++) {
      const M = f[P], k = d[P], G = (
        // oldVNode may be an errored async setup() component inside Suspense
        // which will not have a mounted element
        M.el && // - In the case of a Fragment, we need to provide the actual parent
        // of the Fragment itself so it can move its children.
        (M.type === Z || // - In the case of different nodes, there is going to be a replacement
        // which also requires the correct parent container
        !wt(M, k) || // - In the case of a component, it could contain anything.
        M.shapeFlag & 198) ? h(M.el) : (
          // In other cases, the parent container is not actually used so we
          // just pass the block element here to avoid a DOM parentNode call.
          v
        )
      );
      F(
        M,
        k,
        G,
        null,
        C,
        b,
        w,
        A,
        !0
      );
    }
  }, ne = (f, d, v, C, b) => {
    if (d !== v) {
      if (d !== ae)
        for (const w in d)
          !Jt(w) && !(w in v) && i(
            f,
            w,
            d[w],
            null,
            b,
            C
          );
      for (const w in v) {
        if (Jt(w)) continue;
        const A = v[w], P = d[w];
        A !== P && w !== "value" && i(f, w, P, A, b, C);
      }
      "value" in v && i(f, "value", d.value, v.value, b);
    }
  }, j = (f, d, v, C, b, w, A, P, M) => {
    const k = d.el = f ? f.el : l(""), G = d.anchor = f ? f.anchor : l("");
    let { patchFlag: O, dynamicChildren: K, slotScopeIds: z } = d;
    z && (P = P ? P.concat(z) : z), f == null ? (n(k, v, C), n(G, v, C), H(
      // #10007
      // such fragment like `<></>` will be compiled into
      // a fragment which doesn't have a children.
      // In this case fallback to an empty array
      d.children || [],
      v,
      G,
      b,
      w,
      A,
      P,
      M
    )) : O > 0 && O & 64 && K && // #2715 the previous fragment could've been a BAILed one as a result
    // of renderSlot() with no valid children
    f.dynamicChildren && f.dynamicChildren.length === K.length ? (ee(
      f.dynamicChildren,
      K,
      v,
      b,
      w,
      A,
      P
    ), // #2080 if the stable fragment has a key, it's a <template v-for> that may
    //  get moved around. Make sure all root level vnodes inherit el.
    // #2134 or if it's a component root, it may also get moved around
    // as the component is being moved.
    (d.key != null || b && d === b.subTree) && Zn(
      f,
      d,
      !0
      /* shallow */
    )) : ue(
      f,
      d,
      v,
      G,
      b,
      w,
      A,
      P,
      M
    );
  }, D = (f, d, v, C, b, w, A, P, M) => {
    d.slotScopeIds = P, f == null ? d.shapeFlag & 512 ? b.ctx.activate(
      d,
      v,
      C,
      A,
      M
    ) : N(
      d,
      v,
      C,
      b,
      w,
      A,
      M
    ) : x(f, d, M);
  }, N = (f, d, v, C, b, w, A) => {
    const P = f.component = sc(
      f,
      C,
      b
    );
    if (Ks(f) && (P.ctx.renderer = Dt), nc(P, !1, A), P.asyncDep) {
      if (b && b.registerDep(P, W, A), !f.el) {
        const M = P.subTree = be($e);
        B(null, M, d, v), f.placeholder = M.el;
      }
    } else
      W(
        P,
        f,
        d,
        v,
        b,
        w,
        A
      );
  }, x = (f, d, v) => {
    const C = d.component = f.component;
    if (Dl(f, d, v))
      if (C.asyncDep && !C.asyncResolved) {
        he(C, d, v);
        return;
      } else
        C.next = d, C.update();
    else
      d.el = f.el, C.vnode = d;
  }, W = (f, d, v, C, b, w, A) => {
    const P = () => {
      if (f.isMounted) {
        let { next: O, bu: K, u: z, parent: te, vnode: fe } = f;
        {
          const Ue = Zi(f);
          if (Ue) {
            O && (O.el = fe.el, he(f, O, A)), Ue.asyncDep.then(() => {
              ye(() => {
                f.isUnmounted || k();
              }, b);
            });
            return;
          }
        }
        let le = O, Se;
        vt(f, !1), O ? (O.el = fe.el, he(f, O, A)) : O = fe, K && xs(K), (Se = O.props && O.props.onVnodeBeforeUpdate) && qe(Se, te, O, fe), vt(f, !0);
        const Me = xr(f), Be = f.subTree;
        f.subTree = Me, F(
          Be,
          Me,
          // parent may have changed if it's in a teleport
          h(Be.el),
          // anchor may have changed if it's in a fragment
          ps(Be),
          f,
          b,
          w
        ), O.el = Me.el, le === null && Rl(f, Me.el), z && ye(z, b), (Se = O.props && O.props.onVnodeUpdated) && ye(
          () => qe(Se, te, O, fe),
          b
        );
      } else {
        let O;
        const { el: K, props: z } = d, { bm: te, m: fe, parent: le, root: Se, type: Me } = f, Be = Xt(d);
        vt(f, !1), te && xs(te), !Be && (O = z && z.onVnodeBeforeMount) && qe(O, le, d), vt(f, !0);
        {
          Se.ce && Se.ce._hasShadowRoot() && Se.ce._injectChildStyle(
            Me,
            f.parent ? f.parent.type : void 0
          );
          const Ue = f.subTree = xr(f);
          F(
            null,
            Ue,
            v,
            C,
            f,
            b,
            w
          ), d.el = Ue.el;
        }
        if (fe && ye(fe, b), !Be && (O = z && z.onVnodeMounted)) {
          const Ue = d;
          ye(
            () => qe(O, le, Ue),
            b
          );
        }
        (d.shapeFlag & 256 || le && Xt(le.vnode) && le.vnode.shapeFlag & 256) && f.a && ye(f.a, b), f.isMounted = !0, d = v = C = null;
      }
    };
    f.scope.on();
    const M = f.effect = new ri(P);
    f.scope.off();
    const k = f.update = M.run.bind(M), G = f.job = M.runIfDirty.bind(M);
    G.i = f, G.id = f.uid, M.scheduler = () => Kn(G), vt(f, !0), k();
  }, he = (f, d, v) => {
    d.component = f;
    const C = f.vnode.props;
    f.vnode = d, f.next = null, Nl(f, d.props, C, v), Ul(f, d.children, v), ct(), ar(f), at();
  }, ue = (f, d, v, C, b, w, A, P, M = !1) => {
    const k = f && f.children, G = f ? f.shapeFlag : 0, O = d.children, { patchFlag: K, shapeFlag: z } = d;
    if (K > 0) {
      if (K & 128) {
        hs(
          k,
          O,
          v,
          C,
          b,
          w,
          A,
          P,
          M
        );
        return;
      } else if (K & 256) {
        _t(
          k,
          O,
          v,
          C,
          b,
          w,
          A,
          P,
          M
        );
        return;
      }
    }
    z & 8 ? (G & 16 && Ot(k, b, w), O !== k && a(v, O)) : G & 16 ? z & 16 ? hs(
      k,
      O,
      v,
      C,
      b,
      w,
      A,
      P,
      M
    ) : Ot(k, b, w, !0) : (G & 8 && a(v, ""), z & 16 && H(
      O,
      v,
      C,
      b,
      w,
      A,
      P,
      M
    ));
  }, _t = (f, d, v, C, b, w, A, P, M) => {
    f = f || Pt, d = d || Pt;
    const k = f.length, G = d.length, O = Math.min(k, G);
    let K;
    for (K = 0; K < O; K++) {
      const z = d[K] = M ? it(d[K]) : Ze(d[K]);
      F(
        f[K],
        z,
        v,
        null,
        b,
        w,
        A,
        P,
        M
      );
    }
    k > G ? Ot(
      f,
      b,
      w,
      !0,
      !1,
      O
    ) : H(
      d,
      v,
      C,
      b,
      w,
      A,
      P,
      M,
      O
    );
  }, hs = (f, d, v, C, b, w, A, P, M) => {
    let k = 0;
    const G = d.length;
    let O = f.length - 1, K = G - 1;
    for (; k <= O && k <= K; ) {
      const z = f[k], te = d[k] = M ? it(d[k]) : Ze(d[k]);
      if (wt(z, te))
        F(
          z,
          te,
          v,
          null,
          b,
          w,
          A,
          P,
          M
        );
      else
        break;
      k++;
    }
    for (; k <= O && k <= K; ) {
      const z = f[O], te = d[K] = M ? it(d[K]) : Ze(d[K]);
      if (wt(z, te))
        F(
          z,
          te,
          v,
          null,
          b,
          w,
          A,
          P,
          M
        );
      else
        break;
      O--, K--;
    }
    if (k > O) {
      if (k <= K) {
        const z = K + 1, te = z < G ? d[z].el : C;
        for (; k <= K; )
          F(
            null,
            d[k] = M ? it(d[k]) : Ze(d[k]),
            v,
            te,
            b,
            w,
            A,
            P,
            M
          ), k++;
      }
    } else if (k > K)
      for (; k <= O; )
        We(f[k], b, w, !0), k++;
    else {
      const z = k, te = k, fe = /* @__PURE__ */ new Map();
      for (k = te; k <= K; k++) {
        const Ae = d[k] = M ? it(d[k]) : Ze(d[k]);
        Ae.key != null && fe.set(Ae.key, k);
      }
      let le, Se = 0;
      const Me = K - te + 1;
      let Be = !1, Ue = 0;
      const Rt = new Array(Me);
      for (k = 0; k < Me; k++) Rt[k] = 0;
      for (k = z; k <= O; k++) {
        const Ae = f[k];
        if (Se >= Me) {
          We(Ae, b, w, !0);
          continue;
        }
        let Ke;
        if (Ae.key != null)
          Ke = fe.get(Ae.key);
        else
          for (le = te; le <= K; le++)
            if (Rt[le - te] === 0 && wt(Ae, d[le])) {
              Ke = le;
              break;
            }
        Ke === void 0 ? We(Ae, b, w, !0) : (Rt[Ke - te] = k + 1, Ke >= Ue ? Ue = Ke : Be = !0, F(
          Ae,
          d[Ke],
          v,
          null,
          b,
          w,
          A,
          P,
          M
        ), Se++);
      }
      const sr = Be ? Jl(Rt) : Pt;
      for (le = sr.length - 1, k = Me - 1; k >= 0; k--) {
        const Ae = te + k, Ke = d[Ae], nr = d[Ae + 1], rr = Ae + 1 < G ? (
          // #13559, #14173 fallback to el placeholder for unresolved async component
          nr.el || Yi(nr)
        ) : C;
        Rt[k] === 0 ? F(
          null,
          Ke,
          v,
          rr,
          b,
          w,
          A,
          P,
          M
        ) : Be && (le < 0 || k !== sr[le] ? gt(Ke, v, rr, 2) : le--);
      }
    }
  }, gt = (f, d, v, C, b = null) => {
    const { el: w, type: A, transition: P, children: M, shapeFlag: k } = f;
    if (k & 6) {
      gt(f.component.subTree, d, v, C);
      return;
    }
    if (k & 128) {
      f.suspense.move(d, v, C);
      return;
    }
    if (k & 64) {
      A.move(f, d, v, Dt);
      return;
    }
    if (A === Z) {
      n(w, d, v);
      for (let O = 0; O < M.length; O++)
        gt(M[O], d, v, C);
      n(f.anchor, d, v);
      return;
    }
    if (A === ln) {
      S(f, d, v);
      return;
    }
    if (C !== 2 && k & 1 && P)
      if (C === 0)
        P.beforeEnter(w), n(w, d, v), ye(() => P.enter(w), b);
      else {
        const { leave: O, delayLeave: K, afterLeave: z } = P, te = () => {
          f.ctx.isUnmounted ? r(w) : n(w, d, v);
        }, fe = () => {
          w._isLeaving && w[Je](
            !0
            /* cancelled */
          ), O(w, () => {
            te(), z && z();
          });
        };
        K ? K(w, te, fe) : fe();
      }
    else
      n(w, d, v);
  }, We = (f, d, v, C = !1, b = !1) => {
    const {
      type: w,
      props: A,
      ref: P,
      children: M,
      dynamicChildren: k,
      shapeFlag: G,
      patchFlag: O,
      dirs: K,
      cacheIndex: z
    } = f;
    if (O === -2 && (b = !1), P != null && (ct(), Qt(P, null, v, f, !0), at()), z != null && (d.renderCache[z] = void 0), G & 256) {
      d.ctx.deactivate(f);
      return;
    }
    const te = G & 1 && K, fe = !Xt(f);
    let le;
    if (fe && (le = A && A.onVnodeBeforeUnmount) && qe(le, d, f), G & 6)
      ho(f.component, v, C);
    else {
      if (G & 128) {
        f.suspense.unmount(v, C);
        return;
      }
      te && mt(f, null, d, "beforeUnmount"), G & 64 ? f.type.remove(
        f,
        d,
        v,
        Dt,
        C
      ) : k && // #5154
      // when v-once is used inside a block, setBlockTracking(-1) marks the
      // parent block with hasOnce: true
      // so that it doesn't take the fast path during unmount - otherwise
      // components nested in v-once are never unmounted.
      !k.hasOnce && // #1153: fast path should not be taken for non-stable (v-for) fragments
      (w !== Z || O > 0 && O & 64) ? Ot(
        k,
        d,
        v,
        !1,
        !0
      ) : (w === Z && O & 384 || !b && G & 16) && Ot(M, d, v), C && er(f);
    }
    (fe && (le = A && A.onVnodeUnmounted) || te) && ye(() => {
      le && qe(le, d, f), te && mt(f, null, d, "unmounted");
    }, v);
  }, er = (f) => {
    const { type: d, el: v, anchor: C, transition: b } = f;
    if (d === Z) {
      fo(v, C);
      return;
    }
    if (d === ln) {
      T(f);
      return;
    }
    const w = () => {
      r(v), b && !b.persisted && b.afterLeave && b.afterLeave();
    };
    if (f.shapeFlag & 1 && b && !b.persisted) {
      const { leave: A, delayLeave: P } = b, M = () => A(v, w);
      P ? P(f.el, w, M) : M();
    } else
      w();
  }, fo = (f, d) => {
    let v;
    for (; f !== d; )
      v = y(f), r(f), f = v;
    r(d);
  }, ho = (f, d, v) => {
    const { bum: C, scope: b, job: w, subTree: A, um: P, m: M, a: k } = f;
    kr(M), kr(k), C && xs(C), b.stop(), w && (w.flags |= 8, We(A, f, d, v)), P && ye(P, d), ye(() => {
      f.isUnmounted = !0;
    }, d);
  }, Ot = (f, d, v, C = !1, b = !1, w = 0) => {
    for (let A = w; A < f.length; A++)
      We(f[A], d, v, C, b);
  }, ps = (f) => {
    if (f.shapeFlag & 6)
      return ps(f.component.subTree);
    if (f.shapeFlag & 128)
      return f.suspense.next();
    const d = y(f.anchor || f.el), v = d && d[Ti];
    return v ? y(v) : d;
  };
  let Ys = !1;
  const tr = (f, d, v) => {
    let C;
    f == null ? d._vnode && (We(d._vnode, null, null, !0), C = d._vnode.component) : F(
      d._vnode || null,
      f,
      d,
      null,
      null,
      null,
      v
    ), d._vnode = f, Ys || (Ys = !0, ar(C), bi(), Ys = !1);
  }, Dt = {
    p: F,
    um: We,
    m: gt,
    r: er,
    mt: N,
    mc: H,
    pc: ue,
    pbc: ee,
    n: ps,
    o: e
  };
  return {
    render: tr,
    hydrate: void 0,
    createApp: Al(tr)
  };
}
function on({ type: e, props: t }, s) {
  return s === "svg" && e === "foreignObject" || s === "mathml" && e === "annotation-xml" && t && t.encoding && t.encoding.includes("html") ? void 0 : s;
}
function vt({ effect: e, job: t }, s) {
  s ? (e.flags |= 32, t.flags |= 4) : (e.flags &= -33, t.flags &= -5);
}
function Gl(e, t) {
  return (!e || e && !e.pendingBranch) && t && !t.persisted;
}
function Zn(e, t, s = !1) {
  const n = e.children, r = t.children;
  if (q(n) && q(r))
    for (let i = 0; i < n.length; i++) {
      const o = n[i];
      let l = r[i];
      l.shapeFlag & 1 && !l.dynamicChildren && ((l.patchFlag <= 0 || l.patchFlag === 32) && (l = r[i] = it(r[i]), l.el = o.el), !s && l.patchFlag !== -2 && Zn(o, l)), l.type === Js && (l.patchFlag === -1 && (l = r[i] = it(l)), l.el = o.el), l.type === $e && !l.el && (l.el = o.el);
    }
}
function Jl(e) {
  const t = e.slice(), s = [0];
  let n, r, i, o, l;
  const c = e.length;
  for (n = 0; n < c; n++) {
    const u = e[n];
    if (u !== 0) {
      if (r = s[s.length - 1], e[r] < u) {
        t[n] = r, s.push(n);
        continue;
      }
      for (i = 0, o = s.length - 1; i < o; )
        l = i + o >> 1, e[s[l]] < u ? i = l + 1 : o = l;
      u < e[s[i]] && (i > 0 && (t[n] = s[i - 1]), s[i] = n);
    }
  }
  for (i = s.length, o = s[i - 1]; i-- > 0; )
    s[i] = o, o = t[o];
  return s;
}
function Zi(e) {
  const t = e.subTree.component;
  if (t)
    return t.asyncDep && !t.asyncResolved ? t : Zi(t);
}
function kr(e) {
  if (e)
    for (let t = 0; t < e.length; t++)
      e[t].flags |= 8;
}
function Yi(e) {
  if (e.placeholder)
    return e.placeholder;
  const t = e.component;
  return t ? Yi(t.subTree) : null;
}
const Qi = (e) => e.__isSuspense;
function zl(e, t) {
  t && t.pendingBranch ? q(e) ? t.effects.push(...e) : t.effects.push(e) : rl(e);
}
const Z = /* @__PURE__ */ Symbol.for("v-fgt"), Js = /* @__PURE__ */ Symbol.for("v-txt"), $e = /* @__PURE__ */ Symbol.for("v-cmt"), ln = /* @__PURE__ */ Symbol.for("v-stc"), ts = [];
let Ee = null;
function _(e = !1) {
  ts.push(Ee = e ? null : []);
}
function Zl() {
  ts.pop(), Ee = ts[ts.length - 1] || null;
}
let os = 1;
function Ls(e, t = !1) {
  os += e, e < 0 && Ee && t && (Ee.hasOnce = !0);
}
function Xi(e) {
  return e.dynamicChildren = os > 0 ? Ee || Pt : null, Zl(), os > 0 && Ee && Ee.push(e), e;
}
function m(e, t, s, n, r, i) {
  return Xi(
    p(
      e,
      t,
      s,
      n,
      r,
      i,
      !0
    )
  );
}
function zs(e, t, s, n, r) {
  return Xi(
    be(
      e,
      t,
      s,
      n,
      r,
      !0
    )
  );
}
function As(e) {
  return e ? e.__v_isVNode === !0 : !1;
}
function wt(e, t) {
  return e.type === t.type && e.key === t.key;
}
const eo = ({ key: e }) => e ?? null, ks = ({
  ref: e,
  ref_key: t,
  ref_for: s
}) => (typeof e == "number" && (e = "" + e), e != null ? pe(e) || /* @__PURE__ */ we(e) || X(e) ? { i: Fe, r: e, k: t, f: !!s } : e : null);
function p(e, t = null, s = null, n = 0, r = null, i = e === Z ? 0 : 1, o = !1, l = !1) {
  const c = {
    __v_isVNode: !0,
    __v_skip: !0,
    type: e,
    props: t,
    key: t && eo(t),
    ref: t && ks(t),
    scopeId: ki,
    slotScopeIds: null,
    children: s,
    component: null,
    suspense: null,
    ssContent: null,
    ssFallback: null,
    dirs: null,
    transition: null,
    el: null,
    anchor: null,
    target: null,
    targetStart: null,
    targetAnchor: null,
    staticCount: 0,
    shapeFlag: i,
    patchFlag: n,
    dynamicProps: r,
    dynamicChildren: null,
    appContext: null,
    ctx: Fe
  };
  return l ? (Yn(c, s), i & 128 && e.normalize(c)) : s && (c.shapeFlag |= pe(s) ? 8 : 16), os > 0 && // avoid a block node from tracking itself
  !o && // has current parent block
  Ee && // presence of a patch flag indicates this node needs patching on updates.
  // component nodes also should always be patched, because even if the
  // component doesn't need to update, it needs to persist the instance on to
  // the next vnode so that it can be properly unmounted later.
  (c.patchFlag > 0 || i & 6) && // the EVENTS flag is only for hydration and if it is the only flag, the
  // vnode should not be considered dynamic due to handler caching.
  c.patchFlag !== 32 && Ee.push(c), c;
}
const be = Yl;
function Yl(e, t = null, s = null, n = 0, r = null, i = !1) {
  if ((!e || e === kl) && (e = $e), As(e)) {
    const l = pt(
      e,
      t,
      !0
      /* mergeRef: true */
    );
    return s && Yn(l, s), os > 0 && !i && Ee && (l.shapeFlag & 6 ? Ee[Ee.indexOf(e)] = l : Ee.push(l)), l.patchFlag = -2, l;
  }
  if (lc(e) && (e = e.__vccOpts), t) {
    t = Ql(t);
    let { class: l, style: c } = t;
    l && !pe(l) && (t.class = _e(l)), oe(c) && (/* @__PURE__ */ Un(c) && !q(c) && (c = ve({}, c)), t.style = Dn(c));
  }
  const o = pe(e) ? 1 : Qi(e) ? 128 : Si(e) ? 64 : oe(e) ? 4 : X(e) ? 2 : 0;
  return p(
    e,
    t,
    s,
    n,
    r,
    o,
    i,
    !0
  );
}
function Ql(e) {
  return e ? /* @__PURE__ */ Un(e) || Ui(e) ? ve({}, e) : e : null;
}
function pt(e, t, s = !1, n = !1) {
  const { props: r, ref: i, patchFlag: o, children: l, transition: c } = e, u = t ? Xl(r || {}, t) : r, a = {
    __v_isVNode: !0,
    __v_skip: !0,
    type: e.type,
    props: u,
    key: u && eo(u),
    ref: t && t.ref ? (
      // #2078 in the case of <component :is="vnode" ref="extra"/>
      // if the vnode itself already has a ref, cloneVNode will need to merge
      // the refs so the single vnode can be set on multiple refs
      s && i ? q(i) ? i.concat(ks(t)) : [i, ks(t)] : ks(t)
    ) : i,
    scopeId: e.scopeId,
    slotScopeIds: e.slotScopeIds,
    children: l,
    target: e.target,
    targetStart: e.targetStart,
    targetAnchor: e.targetAnchor,
    staticCount: e.staticCount,
    shapeFlag: e.shapeFlag,
    // if the vnode is cloned with extra props, we can no longer assume its
    // existing patch flag to be reliable and need to add the FULL_PROPS flag.
    // note: preserve flag for fragments since they use the flag for children
    // fast paths only.
    patchFlag: t && e.type !== Z ? o === -1 ? 16 : o | 16 : o,
    dynamicProps: e.dynamicProps,
    dynamicChildren: e.dynamicChildren,
    appContext: e.appContext,
    dirs: e.dirs,
    transition: c,
    // These should technically only be non-null on mounted VNodes. However,
    // they *should* be copied for kept-alive vnodes. So we just always copy
    // them since them being non-null during a mount doesn't affect the logic as
    // they will simply be overwritten.
    component: e.component,
    suspense: e.suspense,
    ssContent: e.ssContent && pt(e.ssContent),
    ssFallback: e.ssFallback && pt(e.ssFallback),
    placeholder: e.placeholder,
    el: e.el,
    anchor: e.anchor,
    ctx: e.ctx,
    ce: e.ce
  };
  return c && n && is(
    a,
    c.clone(a)
  ), a;
}
function me(e = " ", t = 0) {
  return be(Js, null, e, t);
}
function V(e = "", t = !1) {
  return t ? (_(), zs($e, null, e)) : be($e, null, e);
}
function Ze(e) {
  return e == null || typeof e == "boolean" ? be($e) : q(e) ? be(
    Z,
    null,
    // #3666, avoid reference pollution when reusing vnode
    e.slice()
  ) : As(e) ? it(e) : be(Js, null, String(e));
}
function it(e) {
  return e.el === null && e.patchFlag !== -1 || e.memo ? e : pt(e);
}
function Yn(e, t) {
  let s = 0;
  const { shapeFlag: n } = e;
  if (t == null)
    t = null;
  else if (q(t))
    s = 16;
  else if (typeof t == "object")
    if (n & 65) {
      const r = t.default;
      r && (r._c && (r._d = !1), Yn(e, r()), r._c && (r._d = !0));
      return;
    } else {
      s = 32;
      const r = t._;
      !r && !Ui(t) ? t._ctx = Fe : r === 3 && Fe && (Fe.slots._ === 1 ? t._ = 1 : (t._ = 2, e.patchFlag |= 1024));
    }
  else X(t) ? (t = { default: t, _ctx: Fe }, s = 32) : (t = String(t), n & 64 ? (s = 16, t = [me(t)]) : s = 8);
  e.children = t, e.shapeFlag |= s;
}
function Xl(...e) {
  const t = {};
  for (let s = 0; s < e.length; s++) {
    const n = e[s];
    for (const r in n)
      if (r === "class")
        t.class !== n.class && (t.class = _e([t.class, n.class]));
      else if (r === "style")
        t.style = Dn([t.style, n.style]);
      else if (Ds(r)) {
        const i = t[r], o = n[r];
        o && i !== o && !(q(i) && i.includes(o)) && (t[r] = i ? [].concat(i, o) : o);
      } else r !== "" && (t[r] = n[r]);
  }
  return t;
}
function qe(e, t, s, n = null) {
  Ve(e, t, 7, [
    s,
    n
  ]);
}
const ec = ji();
let tc = 0;
function sc(e, t, s) {
  const n = e.type, r = (t ? t.appContext : e.appContext) || ec, i = {
    uid: tc++,
    vnode: e,
    type: n,
    parent: t,
    appContext: r,
    root: null,
    // to be immediately set
    next: null,
    subTree: null,
    // will be set synchronously right after creation
    effect: null,
    update: null,
    // will be set synchronously right after creation
    job: null,
    scope: new To(
      !0
      /* detached */
    ),
    render: null,
    proxy: null,
    exposed: null,
    exposeProxy: null,
    withProxy: null,
    provides: t ? t.provides : Object.create(r.provides),
    ids: t ? t.ids : ["", 0, 0],
    accessCache: null,
    renderCache: [],
    // local resolved assets
    components: null,
    directives: null,
    // resolved props and emits options
    propsOptions: qi(n, r),
    emitsOptions: Ni(n, r),
    // emit
    emit: null,
    // to be set immediately
    emitted: null,
    // props default value
    propsDefaults: ae,
    // inheritAttrs
    inheritAttrs: n.inheritAttrs,
    // state
    ctx: ae,
    data: ae,
    props: ae,
    attrs: ae,
    slots: ae,
    refs: ae,
    setupState: ae,
    setupContext: null,
    // suspense related
    suspense: s,
    suspenseId: s ? s.pendingId : 0,
    asyncDep: null,
    asyncResolved: !1,
    // lifecycle hooks
    // not using enums here because it results in computed properties
    isMounted: !1,
    isUnmounted: !1,
    isDeactivated: !1,
    bc: null,
    c: null,
    bm: null,
    m: null,
    bu: null,
    u: null,
    um: null,
    bum: null,
    da: null,
    a: null,
    rtg: null,
    rtc: null,
    ec: null,
    sp: null
  };
  return i.ctx = { _: i }, i.root = t ? t.root : i, i.emit = Il.bind(null, i), e.ce && e.ce(i), i;
}
let Te = null;
const to = () => Te || Fe;
let Es, $n;
{
  const e = Ns(), t = (s, n) => {
    let r;
    return (r = e[s]) || (r = e[s] = []), r.push(n), (i) => {
      r.length > 1 ? r.forEach((o) => o(i)) : r[0](i);
    };
  };
  Es = t(
    "__VUE_INSTANCE_SETTERS__",
    (s) => Te = s
  ), $n = t(
    "__VUE_SSR_SETTERS__",
    (s) => ls = s
  );
}
const ds = (e) => {
  const t = Te;
  return Es(e), e.scope.on(), () => {
    e.scope.off(), Es(t);
  };
}, Cr = () => {
  Te && Te.scope.off(), Es(null);
};
function so(e) {
  return e.vnode.shapeFlag & 4;
}
let ls = !1;
function nc(e, t = !1, s = !1) {
  t && $n(t);
  const { props: n, children: r } = e.vnode, i = so(e);
  jl(e, n, i, t), Bl(e, r, s || t);
  const o = i ? rc(e, t) : void 0;
  return t && $n(!1), o;
}
function rc(e, t) {
  const s = e.type;
  e.accessCache = /* @__PURE__ */ Object.create(null), e.proxy = new Proxy(e.ctx, Cl);
  const { setup: n } = s;
  if (n) {
    ct();
    const r = e.setupContext = n.length > 1 ? oc(e) : null, i = ds(e), o = fs(
      n,
      e,
      0,
      [
        e.props,
        r
      ]
    ), l = zr(o);
    if (at(), i(), (l || e.sp) && !Xt(e) && Hi(e), l) {
      if (o.then(Cr, Cr), t)
        return o.then((c) => {
          $r(e, c);
        }).catch((c) => {
          Bs(c, e, 0);
        });
      e.asyncDep = o;
    } else
      $r(e, o);
  } else
    no(e);
}
function $r(e, t, s) {
  X(t) ? e.type.__ssrInlineRender ? e.ssrRender = t : e.render = t : oe(t) && (e.setupState = vi(t)), no(e);
}
function no(e, t, s) {
  const n = e.type;
  e.render || (e.render = n.render || Qe);
  {
    const r = ds(e);
    ct();
    try {
      $l(e);
    } finally {
      at(), r();
    }
  }
}
const ic = {
  get(e, t) {
    return xe(e, "get", ""), e[t];
  }
};
function oc(e) {
  const t = (s) => {
    e.exposed = s || {};
  };
  return {
    attrs: new Proxy(e.attrs, ic),
    slots: e.slots,
    emit: e.emit,
    expose: t
  };
}
function Zs(e) {
  return e.exposed ? e.exposeProxy || (e.exposeProxy = new Proxy(vi(Go(e.exposed)), {
    get(t, s) {
      if (s in t)
        return t[s];
      if (s in es)
        return es[s](e);
    },
    has(t, s) {
      return s in t || s in es;
    }
  })) : e.proxy;
}
function lc(e) {
  return X(e) && "__vccOpts" in e;
}
const ge = (e, t) => /* @__PURE__ */ Qo(e, t, ls);
function ro(e, t, s) {
  try {
    Ls(-1);
    const n = arguments.length;
    return n === 2 ? oe(t) && !q(t) ? As(t) ? be(e, null, [t]) : be(e, t) : be(e, null, t) : (n > 3 ? s = Array.prototype.slice.call(arguments, 2) : n === 3 && As(s) && (s = [s]), be(e, t, s));
  } finally {
    Ls(1);
  }
}
const cc = "3.5.30";
/**
* @vue/runtime-dom v3.5.30
* (c) 2018-present Yuxi (Evan) You and Vue contributors
* @license MIT
**/
let Tn;
const Tr = typeof window < "u" && window.trustedTypes;
if (Tr)
  try {
    Tn = /* @__PURE__ */ Tr.createPolicy("vue", {
      createHTML: (e) => e
    });
  } catch {
  }
const io = Tn ? (e) => Tn.createHTML(e) : (e) => e, ac = "http://www.w3.org/2000/svg", uc = "http://www.w3.org/1998/Math/MathML", rt = typeof document < "u" ? document : null, Sr = rt && /* @__PURE__ */ rt.createElement("template"), fc = {
  insert: (e, t, s) => {
    t.insertBefore(e, s || null);
  },
  remove: (e) => {
    const t = e.parentNode;
    t && t.removeChild(e);
  },
  createElement: (e, t, s, n) => {
    const r = t === "svg" ? rt.createElementNS(ac, e) : t === "mathml" ? rt.createElementNS(uc, e) : s ? rt.createElement(e, { is: s }) : rt.createElement(e);
    return e === "select" && n && n.multiple != null && r.setAttribute("multiple", n.multiple), r;
  },
  createText: (e) => rt.createTextNode(e),
  createComment: (e) => rt.createComment(e),
  setText: (e, t) => {
    e.nodeValue = t;
  },
  setElementText: (e, t) => {
    e.textContent = t;
  },
  parentNode: (e) => e.parentNode,
  nextSibling: (e) => e.nextSibling,
  querySelector: (e) => rt.querySelector(e),
  setScopeId(e, t) {
    e.setAttribute(t, "");
  },
  // __UNSAFE__
  // Reason: innerHTML.
  // Static content here can only come from compiled templates.
  // As long as the user only uses trusted templates, this is safe.
  insertStaticContent(e, t, s, n, r, i) {
    const o = s ? s.previousSibling : t.lastChild;
    if (r && (r === i || r.nextSibling))
      for (; t.insertBefore(r.cloneNode(!0), s), !(r === i || !(r = r.nextSibling)); )
        ;
    else {
      Sr.innerHTML = io(
        n === "svg" ? `<svg>${e}</svg>` : n === "mathml" ? `<math>${e}</math>` : e
      );
      const l = Sr.content;
      if (n === "svg" || n === "mathml") {
        const c = l.firstChild;
        for (; c.firstChild; )
          l.appendChild(c.firstChild);
        l.removeChild(c);
      }
      t.insertBefore(l, s);
    }
    return [
      // first
      o ? o.nextSibling : t.firstChild,
      // last
      s ? s.previousSibling : t.lastChild
    ];
  }
}, dt = "transition", Vt = "animation", cs = /* @__PURE__ */ Symbol("_vtc"), oo = {
  name: String,
  type: String,
  css: {
    type: Boolean,
    default: !0
  },
  duration: [String, Number, Object],
  enterFromClass: String,
  enterActiveClass: String,
  enterToClass: String,
  appearFromClass: String,
  appearActiveClass: String,
  appearToClass: String,
  leaveFromClass: String,
  leaveActiveClass: String,
  leaveToClass: String
}, dc = /* @__PURE__ */ ve(
  {},
  Pi,
  oo
), hc = (e) => (e.displayName = "Transition", e.props = dc, e), Qn = /* @__PURE__ */ hc(
  (e, { slots: t }) => ro(dl, pc(e), t)
), yt = (e, t = []) => {
  q(e) ? e.forEach((s) => s(...t)) : e && e(...t);
}, Mr = (e) => e ? q(e) ? e.some((t) => t.length > 1) : e.length > 1 : !1;
function pc(e) {
  const t = {};
  for (const j in e)
    j in oo || (t[j] = e[j]);
  if (e.css === !1)
    return t;
  const {
    name: s = "v",
    type: n,
    duration: r,
    enterFromClass: i = `${s}-enter-from`,
    enterActiveClass: o = `${s}-enter-active`,
    enterToClass: l = `${s}-enter-to`,
    appearFromClass: c = i,
    appearActiveClass: u = o,
    appearToClass: a = l,
    leaveFromClass: h = `${s}-leave-from`,
    leaveActiveClass: y = `${s}-leave-active`,
    leaveToClass: $ = `${s}-leave-to`
  } = e, R = _c(r), F = R && R[0], Q = R && R[1], {
    onBeforeEnter: B,
    onEnter: I,
    onEnterCancelled: S,
    onLeave: T,
    onLeaveCancelled: Y,
    onBeforeAppear: J = B,
    onAppear: se = I,
    onAppearCancelled: H = S
  } = t, E = (j, D, N, x) => {
    j._enterCancelled = x, xt(j, D ? a : l), xt(j, D ? u : o), N && N();
  }, ee = (j, D) => {
    j._isLeaving = !1, xt(j, h), xt(j, $), xt(j, y), D && D();
  }, ne = (j) => (D, N) => {
    const x = j ? se : I, W = () => E(D, j, N);
    yt(x, [D, W]), Pr(() => {
      xt(D, j ? c : i), tt(D, j ? a : l), Mr(x) || Lr(D, n, F, W);
    });
  };
  return ve(t, {
    onBeforeEnter(j) {
      yt(B, [j]), tt(j, i), tt(j, o);
    },
    onBeforeAppear(j) {
      yt(J, [j]), tt(j, c), tt(j, u);
    },
    onEnter: ne(!1),
    onAppear: ne(!0),
    onLeave(j, D) {
      j._isLeaving = !0;
      const N = () => ee(j, D);
      tt(j, h), j._enterCancelled ? (tt(j, y), Ir(j)) : (Ir(j), tt(j, y)), Pr(() => {
        j._isLeaving && (xt(j, h), tt(j, $), Mr(T) || Lr(j, n, Q, N));
      }), yt(T, [j, N]);
    },
    onEnterCancelled(j) {
      E(j, !1, void 0, !0), yt(S, [j]);
    },
    onAppearCancelled(j) {
      E(j, !0, void 0, !0), yt(H, [j]);
    },
    onLeaveCancelled(j) {
      ee(j), yt(Y, [j]);
    }
  });
}
function _c(e) {
  if (e == null)
    return null;
  if (oe(e))
    return [cn(e.enter), cn(e.leave)];
  {
    const t = cn(e);
    return [t, t];
  }
}
function cn(e) {
  return vo(e);
}
function tt(e, t) {
  t.split(/\s+/).forEach((s) => s && e.classList.add(s)), (e[cs] || (e[cs] = /* @__PURE__ */ new Set())).add(t);
}
function xt(e, t) {
  t.split(/\s+/).forEach((n) => n && e.classList.remove(n));
  const s = e[cs];
  s && (s.delete(t), s.size || (e[cs] = void 0));
}
function Pr(e) {
  requestAnimationFrame(() => {
    requestAnimationFrame(e);
  });
}
let gc = 0;
function Lr(e, t, s, n) {
  const r = e._endId = ++gc, i = () => {
    r === e._endId && n();
  };
  if (s != null)
    return setTimeout(i, s);
  const { type: o, timeout: l, propCount: c } = mc(e, t);
  if (!o)
    return n();
  const u = o + "end";
  let a = 0;
  const h = () => {
    e.removeEventListener(u, y), i();
  }, y = ($) => {
    $.target === e && ++a >= c && h();
  };
  setTimeout(() => {
    a < c && h();
  }, l + 1), e.addEventListener(u, y);
}
function mc(e, t) {
  const s = window.getComputedStyle(e), n = (R) => (s[R] || "").split(", "), r = n(`${dt}Delay`), i = n(`${dt}Duration`), o = Ar(r, i), l = n(`${Vt}Delay`), c = n(`${Vt}Duration`), u = Ar(l, c);
  let a = null, h = 0, y = 0;
  t === dt ? o > 0 && (a = dt, h = o, y = i.length) : t === Vt ? u > 0 && (a = Vt, h = u, y = c.length) : (h = Math.max(o, u), a = h > 0 ? o > u ? dt : Vt : null, y = a ? a === dt ? i.length : c.length : 0);
  const $ = a === dt && /\b(?:transform|all)(?:,|$)/.test(
    n(`${dt}Property`).toString()
  );
  return {
    type: a,
    timeout: h,
    propCount: y,
    hasTransform: $
  };
}
function Ar(e, t) {
  for (; e.length < t.length; )
    e = e.concat(e);
  return Math.max(...t.map((s, n) => Er(s) + Er(e[n])));
}
function Er(e) {
  return e === "auto" ? 0 : Number(e.slice(0, -1).replace(",", ".")) * 1e3;
}
function Ir(e) {
  return (e ? e.ownerDocument : document).body.offsetHeight;
}
function vc(e, t, s) {
  const n = e[cs];
  n && (t = (t ? [t, ...n] : [...n]).join(" ")), t == null ? e.removeAttribute("class") : s ? e.setAttribute("class", t) : e.className = t;
}
const Is = /* @__PURE__ */ Symbol("_vod"), lo = /* @__PURE__ */ Symbol("_vsh"), Hr = {
  // used for prop mismatch check during hydration
  name: "show",
  beforeMount(e, { value: t }, { transition: s }) {
    e[Is] = e.style.display === "none" ? "" : e.style.display, s && t ? s.beforeEnter(e) : Wt(e, t);
  },
  mounted(e, { value: t }, { transition: s }) {
    s && t && s.enter(e);
  },
  updated(e, { value: t, oldValue: s }, { transition: n }) {
    !t != !s && (n ? t ? (n.beforeEnter(e), Wt(e, !0), n.enter(e)) : n.leave(e, () => {
      Wt(e, !1);
    }) : Wt(e, t));
  },
  beforeUnmount(e, { value: t }) {
    Wt(e, t);
  }
};
function Wt(e, t) {
  e.style.display = t ? e[Is] : "none", e[lo] = !t;
}
const yc = /* @__PURE__ */ Symbol(""), xc = /(?:^|;)\s*display\s*:/;
function bc(e, t, s) {
  const n = e.style, r = pe(s);
  let i = !1;
  if (s && !r) {
    if (t)
      if (pe(t))
        for (const o of t.split(";")) {
          const l = o.slice(0, o.indexOf(":")).trim();
          s[l] == null && Cs(n, l, "");
        }
      else
        for (const o in t)
          s[o] == null && Cs(n, o, "");
    for (const o in s)
      o === "display" && (i = !0), Cs(n, o, s[o]);
  } else if (r) {
    if (t !== s) {
      const o = n[yc];
      o && (s += ";" + o), n.cssText = s, i = xc.test(s);
    }
  } else t && e.removeAttribute("style");
  Is in e && (e[Is] = i ? n.display : "", e[lo] && (n.display = "none"));
}
const Fr = /\s*!important$/;
function Cs(e, t, s) {
  if (q(s))
    s.forEach((n) => Cs(e, t, n));
  else if (s == null && (s = ""), t.startsWith("--"))
    e.setProperty(t, s);
  else {
    const n = wc(e, t);
    Fr.test(s) ? e.setProperty(
      Tt(n),
      s.replace(Fr, ""),
      "important"
    ) : e[n] = s;
  }
}
const Or = ["Webkit", "Moz", "ms"], an = {};
function wc(e, t) {
  const s = an[t];
  if (s)
    return s;
  let n = Re(t);
  if (n !== "filter" && n in e)
    return an[t] = n;
  n = Qr(n);
  for (let r = 0; r < Or.length; r++) {
    const i = Or[r] + n;
    if (i in e)
      return an[t] = i;
  }
  return t;
}
const Dr = "http://www.w3.org/1999/xlink";
function Rr(e, t, s, n, r, i = Co(t)) {
  n && t.startsWith("xlink:") ? s == null ? e.removeAttributeNS(Dr, t.slice(6, t.length)) : e.setAttributeNS(Dr, t, s) : s == null || i && !ei(s) ? e.removeAttribute(t) : e.setAttribute(
    t,
    i ? "" : Xe(s) ? String(s) : s
  );
}
function jr(e, t, s, n, r) {
  if (t === "innerHTML" || t === "textContent") {
    s != null && (e[t] = t === "innerHTML" ? io(s) : s);
    return;
  }
  const i = e.tagName;
  if (t === "value" && i !== "PROGRESS" && // custom elements may use _value internally
  !i.includes("-")) {
    const l = i === "OPTION" ? e.getAttribute("value") || "" : e.value, c = s == null ? (
      // #11647: value should be set as empty string for null and undefined,
      // but <input type="checkbox"> should be set as 'on'.
      e.type === "checkbox" ? "on" : ""
    ) : String(s);
    (l !== c || !("_value" in e)) && (e.value = c), s == null && e.removeAttribute(t), e._value = s;
    return;
  }
  let o = !1;
  if (s === "" || s == null) {
    const l = typeof e[t];
    l === "boolean" ? s = ei(s) : s == null && l === "string" ? (s = "", o = !0) : l === "number" && (s = 0, o = !0);
  }
  try {
    e[t] = s;
  } catch {
  }
  o && e.removeAttribute(r || t);
}
function kt(e, t, s, n) {
  e.addEventListener(t, s, n);
}
function kc(e, t, s, n) {
  e.removeEventListener(t, s, n);
}
const Nr = /* @__PURE__ */ Symbol("_vei");
function Cc(e, t, s, n, r = null) {
  const i = e[Nr] || (e[Nr] = {}), o = i[t];
  if (n && o)
    o.value = n;
  else {
    const [l, c] = $c(t);
    if (n) {
      const u = i[t] = Mc(
        n,
        r
      );
      kt(e, l, u, c);
    } else o && (kc(e, l, o, c), i[t] = void 0);
  }
}
const Vr = /(?:Once|Passive|Capture)$/;
function $c(e) {
  let t;
  if (Vr.test(e)) {
    t = {};
    let n;
    for (; n = e.match(Vr); )
      e = e.slice(0, e.length - n[0].length), t[n[0].toLowerCase()] = !0;
  }
  return [e[2] === ":" ? e.slice(3) : Tt(e.slice(2)), t];
}
let un = 0;
const Tc = /* @__PURE__ */ Promise.resolve(), Sc = () => un || (Tc.then(() => un = 0), un = Date.now());
function Mc(e, t) {
  const s = (n) => {
    if (!n._vts)
      n._vts = Date.now();
    else if (n._vts <= s.attached)
      return;
    Ve(
      Pc(n, s.value),
      t,
      5,
      [n]
    );
  };
  return s.value = e, s.attached = Sc(), s;
}
function Pc(e, t) {
  if (q(t)) {
    const s = e.stopImmediatePropagation;
    return e.stopImmediatePropagation = () => {
      s.call(e), e._stopped = !0;
    }, t.map(
      (n) => (r) => !r._stopped && n && n(r)
    );
  } else
    return t;
}
const Wr = (e) => e.charCodeAt(0) === 111 && e.charCodeAt(1) === 110 && // lowercase letter
e.charCodeAt(2) > 96 && e.charCodeAt(2) < 123, Lc = (e, t, s, n, r, i) => {
  const o = r === "svg";
  t === "class" ? vc(e, n, o) : t === "style" ? bc(e, s, n) : Ds(t) ? In(t) || Cc(e, t, s, n, i) : (t[0] === "." ? (t = t.slice(1), !0) : t[0] === "^" ? (t = t.slice(1), !1) : Ac(e, t, n, o)) ? (jr(e, t, n), !e.tagName.includes("-") && (t === "value" || t === "checked" || t === "selected") && Rr(e, t, n, o, i, t !== "value")) : /* #11081 force set props for possible async custom element */ e._isVueCE && // #12408 check if it's declared prop or it's async custom element
  (Ec(e, t) || // @ts-expect-error _def is private
  e._def.__asyncLoader && (/[A-Z]/.test(t) || !pe(n))) ? jr(e, Re(t), n, i, t) : (t === "true-value" ? e._trueValue = n : t === "false-value" && (e._falseValue = n), Rr(e, t, n, o));
};
function Ac(e, t, s, n) {
  if (n)
    return !!(t === "innerHTML" || t === "textContent" || t in e && Wr(t) && X(s));
  if (t === "spellcheck" || t === "draggable" || t === "translate" || t === "autocorrect" || t === "sandbox" && e.tagName === "IFRAME" || t === "form" || t === "list" && e.tagName === "INPUT" || t === "type" && e.tagName === "TEXTAREA")
    return !1;
  if (t === "width" || t === "height") {
    const r = e.tagName;
    if (r === "IMG" || r === "VIDEO" || r === "CANVAS" || r === "SOURCE")
      return !1;
  }
  return Wr(t) && pe(s) ? !1 : t in e;
}
function Ec(e, t) {
  const s = (
    // @ts-expect-error _def is private
    e._def.props
  );
  if (!s)
    return !1;
  const n = Re(t);
  return Array.isArray(s) ? s.some((r) => Re(r) === n) : Object.keys(s).some((r) => Re(r) === n);
}
const Hs = (e) => {
  const t = e.props["onUpdate:modelValue"] || !1;
  return q(t) ? (s) => xs(t, s) : t;
};
function Ic(e) {
  e.target.composing = !0;
}
function Br(e) {
  const t = e.target;
  t.composing && (t.composing = !1, t.dispatchEvent(new Event("input")));
}
const It = /* @__PURE__ */ Symbol("_assign");
function Ur(e, t, s) {
  return t && (e = e.trim()), s && (e = On(e)), e;
}
const Kr = {
  created(e, { modifiers: { lazy: t, trim: s, number: n } }, r) {
    e[It] = Hs(r);
    const i = n || r.props && r.props.type === "number";
    kt(e, t ? "change" : "input", (o) => {
      o.target.composing || e[It](Ur(e.value, s, i));
    }), (s || i) && kt(e, "change", () => {
      e.value = Ur(e.value, s, i);
    }), t || (kt(e, "compositionstart", Ic), kt(e, "compositionend", Br), kt(e, "change", Br));
  },
  // set value on mounted so it's after min/max for type="range"
  mounted(e, { value: t }) {
    e.value = t ?? "";
  },
  beforeUpdate(e, { value: t, oldValue: s, modifiers: { lazy: n, trim: r, number: i } }, o) {
    if (e[It] = Hs(o), e.composing) return;
    const l = (i || e.type === "number") && !/^0\d/.test(e.value) ? On(e.value) : e.value, c = t ?? "";
    l !== c && (document.activeElement === e && e.type !== "range" && (n && t === s || r && e.value.trim() === c) || (e.value = c));
  }
}, Hc = {
  // #4096 array checkboxes need to be deep traversed
  deep: !0,
  created(e, t, s) {
    e[It] = Hs(s), kt(e, "change", () => {
      const n = e._modelValue, r = Fc(e), i = e.checked, o = e[It];
      if (q(n)) {
        const l = ti(n, r), c = l !== -1;
        if (i && !c)
          o(n.concat(r));
        else if (!i && c) {
          const u = [...n];
          u.splice(l, 1), o(u);
        }
      } else if (Rs(n)) {
        const l = new Set(n);
        i ? l.add(r) : l.delete(r), o(l);
      } else
        o(co(e, i));
    });
  },
  // set initial checked on mount to wait for true-value/false-value
  mounted: qr,
  beforeUpdate(e, t, s) {
    e[It] = Hs(s), qr(e, t, s);
  }
};
function qr(e, { value: t, oldValue: s }, n) {
  e._modelValue = t;
  let r;
  if (q(t))
    r = ti(t, n.props.value) > -1;
  else if (Rs(t))
    r = t.has(n.props.value);
  else {
    if (t === s) return;
    r = us(t, co(e, !0));
  }
  e.checked !== r && (e.checked = r);
}
function Fc(e) {
  return "_value" in e ? e._value : e.value;
}
function co(e, t) {
  const s = t ? "_trueValue" : "_falseValue";
  return s in e ? e[s] : t;
}
const Oc = ["ctrl", "shift", "alt", "meta"], Dc = {
  stop: (e) => e.stopPropagation(),
  prevent: (e) => e.preventDefault(),
  self: (e) => e.target !== e.currentTarget,
  ctrl: (e) => !e.ctrlKey,
  shift: (e) => !e.shiftKey,
  alt: (e) => !e.altKey,
  meta: (e) => !e.metaKey,
  left: (e) => "button" in e && e.button !== 0,
  middle: (e) => "button" in e && e.button !== 1,
  right: (e) => "button" in e && e.button !== 2,
  exact: (e, t) => Oc.some((s) => e[`${s}Key`] && !t.includes(s))
}, ao = (e, t) => {
  if (!e) return e;
  const s = e._withMods || (e._withMods = {}), n = t.join(".");
  return s[n] || (s[n] = ((r, ...i) => {
    for (let o = 0; o < t.length; o++) {
      const l = Dc[t[o]];
      if (l && l(r, t)) return;
    }
    return e(r, ...i);
  }));
}, Rc = /* @__PURE__ */ ve({ patchProp: Lc }, fc);
let Gr;
function jc() {
  return Gr || (Gr = Kl(Rc));
}
const Nc = ((...e) => {
  const t = jc().createApp(...e), { mount: s } = t;
  return t.mount = (n) => {
    const r = Wc(n);
    if (!r) return;
    const i = t._component;
    !X(i) && !i.render && !i.template && (i.template = r.innerHTML), r.nodeType === 1 && (r.textContent = "");
    const o = s(r, !1, Vc(r));
    return r instanceof Element && (r.removeAttribute("v-cloak"), r.setAttribute("data-v-app", "")), o;
  }, t;
});
function Vc(e) {
  if (e instanceof SVGElement)
    return "svg";
  if (typeof MathMLElement == "function" && e instanceof MathMLElement)
    return "mathml";
}
function Wc(e) {
  return pe(e) ? document.querySelector(e) : e;
}
let Fs = "/api/v1", Sn = null, uo = 15e3;
const Os = /* @__PURE__ */ new Map(), Bc = 6e4;
let Xn = !0;
function Bt(e) {
  if (!Xn) return null;
  const t = Os.get(e);
  return t ? Date.now() - t.ts > Bc ? (Os.delete(e), null) : t.data : null;
}
function Ut(e, t) {
  Xn && Os.set(e, { data: t, ts: Date.now() });
}
function Uc() {
  Os.clear();
}
function Kc({ baseUrl: e, token: t, timeout: s, cache: n }) {
  {
    let r = e.replace(/\/+$/, "");
    typeof window < "u" && window.location.protocol === "https:" && r.startsWith("http://") && (r = r.replace(/^http:\/\//, "https://")), Fs = r;
  }
  t && (Sn = t), s && (uo = s), n === !1 && (Xn = !1);
}
function Mn(e) {
  if (!e) return null;
  if (e.startsWith("http://") || e.startsWith("https://"))
    return typeof window < "u" && window.location.protocol === "https:" && e.startsWith("http://") ? e.replace(/^http:\/\//, "https://") : e;
  if (Fs.startsWith("http"))
    try {
      return new URL(Fs).origin + e;
    } catch {
    }
  return e;
}
async function st(e, t = {}) {
  const s = Fs + e, n = {
    Accept: "application/json",
    "Content-Type": "application/json"
  };
  Sn && (n.Authorization = `Bearer ${Sn}`);
  const r = new AbortController(), i = setTimeout(() => r.abort(), uo);
  try {
    const o = await fetch(s, {
      ...t,
      headers: { ...n, ...t.headers },
      signal: r.signal
    });
    if (clearTimeout(i), !o.ok) {
      const l = new Error(`HTTP ${o.status}`);
      l.status = o.status;
      try {
        l.data = await o.json();
      } catch {
      }
      throw l;
    }
    return o;
  } catch (o) {
    throw clearTimeout(i), o;
  }
}
function Kt(e = {}) {
  const t = new URLSearchParams();
  if (e.page && t.set("page", e.page), e.perPage && t.set("per_page", e.perPage), e.sort && t.set("sort", e.sort), e.order && t.set("order", e.order), e.search && t.set("search", e.search), e.category && t.set("category", e.category), e.hierarchyType && t.set("hierarchy_type", e.hierarchyType), e.lang && t.set("lang", e.lang), e.type && t.set("type", e.type), e.hierarchyId && t.set("hierarchy_id", e.hierarchyId), e.filters)
    for (const [n, r] of Object.entries(e.filters))
      t.set(`filters[${n}]`, r);
  const s = t.toString();
  return s ? `?${s}` : "";
}
const nt = {
  async getProducts(e = {}) {
    const t = `/catalog/products${Kt(e)}`, s = Bt(t);
    if (s) return s;
    const n = await st(t), r = await n.json(), i = {
      products: Array.isArray(r) ? r : r.data || r,
      meta: {
        current_page: parseInt(n.headers.get("x-current-page") || "1", 10),
        last_page: parseInt(n.headers.get("x-last-page") || "1", 10),
        per_page: parseInt(n.headers.get("x-per-page") || "24", 10),
        total: parseInt(n.headers.get("x-total-count") || "0", 10)
      }
    };
    return Ut(t, i), i;
  },
  async getProduct(e, t = {}) {
    const s = `/catalog/products/${e}${Kt(t)}`, n = Bt(s);
    if (n) return n;
    const i = await (await st(s)).json(), o = i.data || i;
    return Ut(s, o), o;
  },
  async getCategories(e = {}) {
    const t = `/catalog/categories${Kt(e)}`, s = Bt(t);
    if (s) return s;
    const r = await (await st(t)).json(), i = r.data || r;
    return Ut(t, i), i;
  },
  async getSettings() {
    const e = "/catalog/settings", t = Bt(e);
    if (t) return t;
    const n = await (await st(e)).json(), r = n.data || n;
    return Ut(e, r), r;
  },
  async getFacets(e = {}) {
    const t = `/catalog/facets${Kt(e)}`, s = Bt(t);
    if (s) return s;
    const r = await (await st(t)).json();
    return Ut(t, r), r;
  },
  async downloadProductPdf(e, t = {}) {
    return (await st(`/catalog/products/${e}/pdf${Kt(t)}`)).blob();
  },
  async downloadWishlistPdf(e, t) {
    return (await st("/catalog/wishlist/pdf", {
      method: "POST",
      body: JSON.stringify({ product_ids: e, lang: t })
    })).blob();
  },
  async downloadWishlistExcel(e) {
    return (await st("/catalog/wishlist/excel", {
      method: "POST",
      body: JSON.stringify({ product_ids: e })
    })).blob();
  },
  async compareProducts(e, t) {
    const n = await (await st("/catalog/products/compare", {
      method: "POST",
      body: JSON.stringify({ product_ids: e, lang: t })
    })).json();
    return n.data || n;
  }
};
function qc() {
  const e = /* @__PURE__ */ Ws({
    // Products
    products: [],
    currentProduct: null,
    loading: !1,
    productLoading: !1,
    error: null,
    // Pagination
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 24,
      total: 0
    },
    // Filters & navigation
    search: "",
    selectedCategoryId: null,
    selectedCategoryName: null,
    sort: { field: "name", order: "asc" },
    viewMode: typeof localStorage < "u" && localStorage.getItem("pxc_view_mode") || "grid",
    locale: typeof localStorage < "u" && localStorage.getItem("pxc_locale") || "de",
    // Categories
    categories: [],
    hierarchyInfo: null,
    categoriesLoading: !1,
    // Facets
    facets: [],
    activeFilters: {},
    // Wishlist
    wishlistIds: JSON.parse(typeof localStorage < "u" && localStorage.getItem("pxc_wishlist") || "[]"),
    // Settings (from PIM)
    settings: {},
    _settingsLoaded: !1,
    // Compare
    compareData: null,
    compareLoading: !1,
    compareOpen: !1,
    compareProductIds: [],
    // Product detail modal/view
    detailOpen: !1,
    detailProductId: null
  });
  typeof localStorage < "u" && (De(() => e.wishlistIds, (n) => {
    localStorage.setItem("pxc_wishlist", JSON.stringify(n));
  }, { deep: !0 }), De(() => e.viewMode, (n) => localStorage.setItem("pxc_view_mode", n)), De(() => e.locale, (n) => localStorage.setItem("pxc_locale", n)));
  const t = {
    isEmpty: ge(() => e.products.length === 0 && !e.loading),
    wishlistCount: ge(() => e.wishlistIds.length),
    searchActive: ge(() => e.search && e.search.trim().length > 0),
    activeFilterCount: ge(() => Object.keys(e.activeFilters).length),
    isInWishlist(n) {
      return e.wishlistIds.includes(n);
    }
  }, s = {
    async fetchSettings() {
      try {
        const n = await nt.getSettings();
        e.settings = n || {}, !(typeof localStorage < "u" && localStorage.getItem("pxc_locale")) && n.default_locale && (e.locale = n.default_locale), e._settingsLoaded = !0;
      } catch (n) {
        console.warn("[PublixxCatalog] Failed to load settings:", n.message);
      }
    },
    async fetchProducts() {
      var n;
      e.loading = !0, e.error = null;
      try {
        const r = e.search && e.search.trim().length > 0, i = await nt.getProducts({
          page: e.meta.current_page,
          perPage: e.meta.per_page,
          sort: e.sort.field,
          order: e.sort.order,
          search: e.search || void 0,
          category: r ? void 0 : e.selectedCategoryId || void 0,
          lang: e.locale,
          filters: r ? void 0 : Object.keys(e.activeFilters).length > 0 ? { ...e.activeFilters } : void 0,
          hierarchyId: e.settings.hierarchy_id || void 0
        });
        e.products = i.products.map((o) => ({
          ...o,
          image_url: Mn(o.image_url)
        })), e.meta = i.meta;
      } catch (r) {
        e.error = ((n = r.data) == null ? void 0 : n.title) || "Fehler beim Laden", e.products = [];
      } finally {
        e.loading = !1;
      }
    },
    async fetchProduct(n) {
      var r;
      e.productLoading = !0, e.error = null;
      try {
        const i = await nt.getProduct(n, { lang: e.locale });
        i != null && i.media && (i.media = i.media.map((o) => ({ ...o, url: Mn(o.url) }))), e.currentProduct = i;
      } catch (i) {
        e.error = ((r = i.data) == null ? void 0 : r.title) || "Produkt nicht gefunden", e.currentProduct = null;
      } finally {
        e.productLoading = !1;
      }
    },
    async fetchCategories() {
      e.categoriesLoading = !0;
      try {
        const n = await nt.getCategories({
          lang: e.locale,
          hierarchyId: e.settings.hierarchy_id || void 0
        });
        e.categories = n.nodes || [], e.hierarchyInfo = {
          hierarchy_id: n.hierarchy_id,
          hierarchy_name: n.hierarchy_name,
          type: n.type
        };
      } catch (n) {
        console.error("[PublixxCatalog] Categories load failed:", n), e.categories = [];
      } finally {
        e.categoriesLoading = !1;
      }
    },
    async fetchFacets() {
      try {
        const n = await nt.getFacets({ lang: e.locale });
        e.facets = n.facets || [];
      } catch (n) {
        console.warn("[PublixxCatalog] Facets load failed:", n.message), e.facets = [];
      }
    },
    // Navigation
    setSearch(n) {
      e.search = n, e.meta.current_page = 1;
    },
    setCategory(n, r = null) {
      e.selectedCategoryId = n, e.selectedCategoryName = r, e.meta.current_page = 1;
    },
    clearCategory() {
      e.selectedCategoryId = null, e.selectedCategoryName = null, e.meta.current_page = 1;
    },
    setPage(n) {
      e.meta.current_page = n;
    },
    setSort(n, r) {
      e.sort = { field: n, order: r }, e.meta.current_page = 1;
    },
    setViewMode(n) {
      e.viewMode = n;
    },
    setLocale(n) {
      e.locale = n, Uc();
    },
    // Filters
    setFilter(n, r) {
      e.activeFilters[n] = r, e.meta.current_page = 1;
    },
    clearFilter(n) {
      delete e.activeFilters[n], e.meta.current_page = 1;
    },
    clearAllFilters() {
      for (const n of Object.keys(e.activeFilters))
        delete e.activeFilters[n];
      e.meta.current_page = 1;
    },
    // Wishlist
    toggleWishlist(n) {
      const r = e.wishlistIds.indexOf(n);
      r === -1 ? e.wishlistIds.push(n) : e.wishlistIds.splice(r, 1);
    },
    clearWishlist() {
      e.wishlistIds.splice(0, e.wishlistIds.length);
    },
    importWishlistFromUrl() {
      const n = new URLSearchParams(window.location.search), r = n.get("wishlist");
      if (!r) return;
      const i = r.split(",").filter(Boolean), o = new Set(e.wishlistIds);
      for (const c of i)
        o.has(c) || e.wishlistIds.push(c);
      n.delete("wishlist");
      const l = n.toString() ? `${window.location.pathname}?${n.toString()}` : window.location.pathname;
      window.history.replaceState({}, "", l);
    },
    // Detail view
    openDetail(n) {
      e.detailProductId = n, e.detailOpen = !0, s.fetchProduct(n);
    },
    closeDetail() {
      e.detailOpen = !1, e.currentProduct = null, e.detailProductId = null;
    },
    // Compare
    async openCompare(n) {
      e.compareProductIds = n || [...e.wishlistIds], e.compareOpen = !0, e.compareLoading = !0;
      try {
        e.compareData = await nt.compareProducts(e.compareProductIds, e.locale);
      } catch (r) {
        console.error("[PublixxCatalog] Compare failed:", r), e.compareData = null;
      } finally {
        e.compareLoading = !1;
      }
    },
    closeCompare() {
      e.compareOpen = !1, e.compareData = null, e.compareProductIds = [];
    },
    // Exports
    async downloadProductPdf(n) {
      const r = await nt.downloadProductPdf(n, { lang: e.locale });
      fn(r, `product-${n}.pdf`);
    },
    async downloadWishlistPdf() {
      const n = await nt.downloadWishlistPdf(e.wishlistIds, e.locale);
      fn(n, `wishlist-${(/* @__PURE__ */ new Date()).toISOString().slice(0, 10)}.pdf`);
    },
    async downloadWishlistExcel() {
      const n = await nt.downloadWishlistExcel(e.wishlistIds);
      fn(n, `wishlist-${(/* @__PURE__ */ new Date()).toISOString().slice(0, 10)}.xlsx`);
    }
  };
  return { state: e, getters: t, actions: s };
}
function fn(e, t) {
  const s = URL.createObjectURL(e), n = document.createElement("a");
  n.href = s, n.download = t, document.body.appendChild(n), n.click(), n.remove(), URL.revokeObjectURL(s);
}
let dn = null;
function Le() {
  return dn || (dn = qc()), dn;
}
const U = {
  search: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>',
  x: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
  heart: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>',
  heartFilled: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>',
  chevronLeft: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>',
  chevronRight: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>',
  chevronDown: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>',
  grid: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>',
  list: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/></svg>',
  fileDown: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M12 18v-6"/><path d="m9 15 3 3 3-3"/></svg>',
  sheet: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><line x1="3" x2="21" y1="9" y2="9"/><line x1="3" x2="21" y1="15" y2="15"/><line x1="9" x2="9" y1="9" y2="21"/><line x1="15" x2="15" y1="9" y2="21"/></svg>',
  compare: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="18" r="3"/><circle cx="6" cy="6" r="3"/><path d="M13 6h3a2 2 0 0 1 2 2v7"/><path d="M11 18H8a2 2 0 0 1-2-2V9"/></svg>',
  share: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" x2="15.42" y1="13.51" y2="17.49"/><line x1="15.41" x2="8.59" y1="6.51" y2="10.49"/></svg>',
  trash: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>',
  check: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
  package: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>',
  folder: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>',
  sortAsc: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 8 4-4 4 4"/><path d="M7 4v16"/><path d="M11 12h4"/><path d="M11 16h7"/><path d="M11 20h10"/></svg>',
  sortDesc: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 16 4 4 4-4"/><path d="M7 20V4"/><path d="M11 4h10"/><path d="M11 8h7"/><path d="M11 12h4"/></svg>',
  globe: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>',
  eye: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>',
  loader: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pxc-spin"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>',
  filter: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>',
  close: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>'
}, Gc = { class: "pxc-search__wrapper" }, Jc = ["innerHTML"], zc = ["value"], Zc = ["innerHTML"], Yc = ["innerHTML"], Qc = {
  __name: "SearchWidget",
  setup(e) {
    const { state: t, actions: s } = Le(), n = /* @__PURE__ */ He(t.search);
    let r = null;
    function i(c) {
      n.value = c.target.value, clearTimeout(r), r = setTimeout(() => {
        s.setSearch(n.value), s.fetchProducts();
      }, 300);
    }
    function o() {
      n.value = "", s.setSearch(""), s.fetchProducts();
    }
    function l(c) {
      c.preventDefault(), clearTimeout(r), s.setSearch(n.value), s.fetchProducts();
    }
    return (c, u) => (_(), m("form", {
      class: "pxc-search",
      onSubmit: l
    }, [
      p("div", Gc, [
        p("span", {
          class: "pxc-search__icon",
          innerHTML: g(U).search
        }, null, 8, Jc),
        p("input", {
          type: "text",
          class: "pxc-search__input",
          value: n.value,
          placeholder: "Produkte suchen...",
          onInput: i
        }, null, 40, zc),
        n.value ? (_(), m("button", {
          key: 0,
          type: "button",
          class: "pxc-search__clear",
          onClick: o,
          innerHTML: g(U).x
        }, null, 8, Zc)) : V("", !0),
        g(t).loading ? (_(), m("span", {
          key: 1,
          class: "pxc-search__loader",
          innerHTML: g(U).loader
        }, null, 8, Yc)) : V("", !0)
      ])
    ], 32));
  }
}, Xc = { class: "pxc-categories" }, ea = { class: "pxc-categories__header" }, ta = ["innerHTML"], sa = { class: "pxc-categories__count" }, na = {
  key: 0,
  class: "pxc-categories__loading"
}, ra = { class: "pxc-categories__row" }, ia = ["onClick", "innerHTML"], oa = {
  key: 1,
  class: "pxc-categories__toggle-space"
}, la = ["onClick"], ca = {
  key: 0,
  class: "pxc-categories__count"
}, aa = { class: "pxc-categories__row" }, ua = ["onClick", "innerHTML"], fa = {
  key: 1,
  class: "pxc-categories__toggle-space"
}, da = ["onClick"], ha = {
  key: 0,
  class: "pxc-categories__count"
}, pa = { class: "pxc-categories__row" }, _a = ["onClick"], ga = {
  key: 0,
  class: "pxc-categories__count"
}, ma = {
  __name: "CategoriesWidget",
  setup(e) {
    const { state: t, actions: s, getters: n } = Le(), r = /* @__PURE__ */ He({});
    Ft(() => {
      t.categories.length === 0 && s.fetchCategories();
    }), De(() => t.locale, () => s.fetchCategories());
    function i(u) {
      s.setCategory(u.id, u.name), s.fetchProducts();
    }
    function o() {
      s.clearCategory(), s.fetchProducts();
    }
    function l(u) {
      r.value[u] = !r.value[u];
    }
    function c(u) {
      return !!r.value[u];
    }
    return (u, a) => (_(), m("div", Xc, [
      p("div", ea, [
        p("span", {
          innerHTML: g(U).folder
        }, null, 8, ta),
        a[0] || (a[0] = p("span", null, "Kategorien", -1))
      ]),
      p("button", {
        class: _e(["pxc-categories__item", { "pxc-categories__item--active": !g(t).selectedCategoryId }]),
        onClick: o
      }, [
        a[1] || (a[1] = me(" Alle Kategorien ", -1)),
        p("span", sa, L(g(t).meta.total), 1)
      ], 2),
      g(t).categoriesLoading ? (_(), m("div", na, [
        (_(), m(Z, null, ce(5, (h) => p("div", {
          key: h,
          class: "pxc-skeleton",
          style: { height: "24px", "margin-bottom": "4px" }
        })), 64))
      ])) : (_(!0), m(Z, { key: 1 }, ce(g(t).categories, (h) => (_(), m("div", {
        key: h.id,
        class: "pxc-categories__node"
      }, [
        p("div", ra, [
          h.children && h.children.length ? (_(), m("button", {
            key: 0,
            class: "pxc-categories__toggle",
            onClick: (y) => l(h.id),
            innerHTML: c(h.id) ? g(U).chevronDown : g(U).chevronRight
          }, null, 8, ia)) : (_(), m("span", oa)),
          p("button", {
            class: _e(["pxc-categories__item", { "pxc-categories__item--active": g(t).selectedCategoryId === h.id }]),
            onClick: (y) => i(h)
          }, [
            me(L(h.name) + " ", 1),
            h.product_count ? (_(), m("span", ca, L(h.product_count), 1)) : V("", !0)
          ], 10, la)
        ]),
        c(h.id) && h.children ? (_(!0), m(Z, { key: 0 }, ce(h.children, (y) => (_(), m("div", {
          key: y.id,
          class: "pxc-categories__node pxc-categories__node--l1"
        }, [
          p("div", aa, [
            y.children && y.children.length ? (_(), m("button", {
              key: 0,
              class: "pxc-categories__toggle",
              onClick: ($) => l(y.id),
              innerHTML: c(y.id) ? g(U).chevronDown : g(U).chevronRight
            }, null, 8, ua)) : (_(), m("span", fa)),
            p("button", {
              class: _e(["pxc-categories__item", { "pxc-categories__item--active": g(t).selectedCategoryId === y.id }]),
              onClick: ($) => i(y)
            }, [
              me(L(y.name) + " ", 1),
              y.product_count ? (_(), m("span", ha, L(y.product_count), 1)) : V("", !0)
            ], 10, da)
          ]),
          c(y.id) && y.children ? (_(!0), m(Z, { key: 0 }, ce(y.children, ($) => (_(), m("div", {
            key: $.id,
            class: "pxc-categories__node pxc-categories__node--l2"
          }, [
            p("div", pa, [
              a[2] || (a[2] = p("span", { class: "pxc-categories__toggle-space" }, null, -1)),
              p("button", {
                class: _e(["pxc-categories__item", { "pxc-categories__item--active": g(t).selectedCategoryId === $.id }]),
                onClick: (R) => i($)
              }, [
                me(L($.name) + " ", 1),
                $.product_count ? (_(), m("span", ga, L($.product_count), 1)) : V("", !0)
              ], 10, _a)
            ])
          ]))), 128)) : V("", !0)
        ]))), 128)) : V("", !0)
      ]))), 128))
    ]));
  }
}, va = ["innerHTML"], ya = {
  key: 0,
  class: "pxc-facets-trigger__badge"
}, xa = { class: "pxc-facets-drawer__header" }, ba = ["innerHTML"], wa = { class: "pxc-facets-drawer__body" }, ka = {
  key: 0,
  class: "pxc-facets"
}, Ca = ["onClick"], $a = ["innerHTML"], Ta = { class: "pxc-facets__group-label" }, Sa = {
  key: 0,
  class: "pxc-facets__badge"
}, Ma = { class: "pxc-facets__body" }, Pa = {
  key: 0,
  class: "pxc-facets__search"
}, La = ["onUpdate:modelValue"], Aa = ["checked", "onChange"], Ea = { class: "pxc-facets__checkbox-label" }, Ia = { class: "pxc-facets__checkbox-count" }, Ha = ["onClick"], Fa = ["onClick"], Oa = {
  key: 1,
  class: "pxc-facets__toggle"
}, Da = ["checked", "onChange"], Ra = {
  key: 2,
  class: "pxc-facets__range"
}, ja = { class: "pxc-facets__range-field" }, Na = ["placeholder", "value", "onChange"], Va = { class: "pxc-facets__range-field" }, Wa = ["placeholder", "value", "onChange"], Ba = {
  key: 0,
  class: "pxc-facets__range-unit"
}, Ua = { class: "pxc-facets-drawer__footer" }, Ka = {
  key: 1,
  class: "pxc-facets"
}, qa = { class: "pxc-facets__header" }, Ga = ["onClick"], Ja = ["innerHTML"], za = { class: "pxc-facets__group-label" }, Za = {
  key: 0,
  class: "pxc-facets__badge"
}, Ya = { class: "pxc-facets__body" }, Qa = {
  key: 0,
  class: "pxc-facets__search"
}, Xa = ["onUpdate:modelValue"], eu = ["checked", "onChange"], tu = { class: "pxc-facets__checkbox-label" }, su = { class: "pxc-facets__checkbox-count" }, nu = ["onClick"], ru = ["onClick"], iu = {
  key: 1,
  class: "pxc-facets__toggle"
}, ou = ["checked", "onChange"], lu = {
  key: 2,
  class: "pxc-facets__range"
}, cu = { class: "pxc-facets__range-field" }, au = ["placeholder", "value", "onChange"], uu = { class: "pxc-facets__range-field" }, fu = ["placeholder", "value", "onChange"], du = {
  key: 0,
  class: "pxc-facets__range-unit"
}, ys = 5, hu = {
  __name: "FacetsWidget",
  props: {
    mode: { type: String, default: "inline" }
    // 'inline' or 'drawer'
  },
  setup(e) {
    const t = e, { state: s, actions: n, getters: r } = Le(), i = /* @__PURE__ */ He({}), o = /* @__PURE__ */ He({}), l = /* @__PURE__ */ He({}), c = /* @__PURE__ */ He(!1);
    function u() {
      c.value = !0;
    }
    function a() {
      c.value = !1;
    }
    function h() {
      c.value = !1;
    }
    Ft(() => {
      s.facets.length === 0 && n.fetchFacets();
    }), De(() => s.locale, () => n.fetchFacets());
    function y(D) {
      i.value[D] = !i.value[D];
    }
    function $(D) {
      return !!i.value[D];
    }
    function R(D) {
      o.value[D] = !o.value[D];
    }
    function F(D) {
      return !!o.value[D];
    }
    function Q(D) {
      const N = s.activeFilters[D];
      return N ? String(N).split(",").filter(Boolean) : [];
    }
    function B(D, N) {
      const x = Q(D), W = x.indexOf(String(N));
      W === -1 ? x.push(String(N)) : x.splice(W, 1), x.length === 0 ? n.clearFilter(D) : n.setFilter(D, x.join(",")), n.fetchProducts();
    }
    function I(D, N) {
      return Q(D).includes(String(N));
    }
    function S(D) {
      s.activeFilters[D] === "1" ? n.clearFilter(D) : n.setFilter(D, "1"), n.fetchProducts();
    }
    function T(D) {
      const N = s.activeFilters[D];
      if (!N) return { min: "", max: "" };
      const x = String(N).split(":");
      return { min: x[0] || "", max: x[1] || "" };
    }
    function Y(D, N) {
      !N.min && !N.max ? n.clearFilter(D) : n.setFilter(D, `${N.min}:${N.max}`), n.fetchProducts();
    }
    function J(D, N) {
      Y(D, { ...T(D), min: N });
    }
    function se(D, N) {
      Y(D, { ...T(D), max: N });
    }
    function H(D) {
      const N = D.values || [], x = (l.value[D.attribute_id] || "").toLowerCase();
      return x ? N.filter((W) => W.value.toLowerCase().includes(x)) : N;
    }
    function E(D) {
      const N = H(D);
      return F(D.attribute_id) ? N : N.slice(0, ys);
    }
    function ee(D) {
      return H(D).length - ys;
    }
    function ne(D) {
      return Q(D).length;
    }
    function j() {
      n.clearAllFilters(), n.fetchProducts();
    }
    return (D, N) => t.mode === "drawer" ? (_(), m(Z, { key: 0 }, [
      g(s).facets.length ? (_(), m("button", {
        key: 0,
        class: "pxc-facets-trigger",
        onClick: u
      }, [
        p("span", {
          innerHTML: g(U).filter || g(U).search
        }, null, 8, va),
        N[1] || (N[1] = p("span", null, "Filtern", -1)),
        g(r).activeFilterCount.value > 0 ? (_(), m("span", ya, L(g(r).activeFilterCount.value), 1)) : V("", !0)
      ])) : V("", !0),
      (_(), zs(qn, { to: "body" }, [
        be(Qn, { name: "pxc-fade" }, {
          default: Us(() => [
            c.value ? (_(), m("div", {
              key: 0,
              class: "pxc-facets-drawer__overlay",
              onClick: a
            })) : V("", !0)
          ]),
          _: 1
        }),
        p("div", {
          class: _e(["pxc-facets-drawer", { "pxc-facets-drawer--open": c.value }])
        }, [
          p("div", xa, [
            N[2] || (N[2] = p("span", { class: "pxc-facets-drawer__title" }, "Produktfilter", -1)),
            p("button", {
              class: "pxc-facets-drawer__close",
              onClick: a
            }, [
              p("span", {
                innerHTML: g(U).close
              }, null, 8, ba)
            ])
          ]),
          p("div", wa, [
            g(s).facets.length ? (_(), m("div", ka, [
              (_(!0), m(Z, null, ce(g(s).facets, (x) => (_(), m("div", {
                key: x.attribute_id,
                class: "pxc-facets__group"
              }, [
                p("button", {
                  class: "pxc-facets__group-header",
                  onClick: (W) => y(x.attribute_id)
                }, [
                  p("span", {
                    innerHTML: $(x.attribute_id) ? g(U).chevronRight : g(U).chevronDown
                  }, null, 8, $a),
                  p("span", Ta, L(x.label), 1),
                  ne(x.attribute_id) > 0 ? (_(), m("span", Sa, L(ne(x.attribute_id)), 1)) : V("", !0)
                ], 8, Ca),
                qt(p("div", Ma, [
                  x.data_type === "ValueList" || x.data_type === "Text" ? (_(), m(Z, { key: 0 }, [
                    (x.values || []).length > 8 ? (_(), m("div", Pa, [
                      qt(p("input", {
                        "onUpdate:modelValue": (W) => l.value[x.attribute_id] = W,
                        type: "text",
                        placeholder: "Suchen...",
                        class: "pxc-facets__search-input"
                      }, null, 8, La), [
                        [Kr, l.value[x.attribute_id]]
                      ])
                    ])) : V("", !0),
                    (_(!0), m(Z, null, ce(E(x), (W) => (_(), m("label", {
                      key: W.value_id || W.value,
                      class: "pxc-facets__checkbox"
                    }, [
                      p("input", {
                        type: "checkbox",
                        checked: I(x.attribute_id, W.value_id || W.value),
                        onChange: (he) => B(x.attribute_id, W.value_id || W.value)
                      }, null, 40, Aa),
                      p("span", Ea, L(W.value), 1),
                      p("span", Ia, L(W.count), 1)
                    ]))), 128)),
                    ee(x) > 0 && !F(x.attribute_id) ? (_(), m("button", {
                      key: 1,
                      class: "pxc-facets__show-more",
                      onClick: (W) => R(x.attribute_id)
                    }, "Mehr anzeigen (+" + L(ee(x)) + ")", 9, Ha)) : F(x.attribute_id) && (x.values || []).length > ys ? (_(), m("button", {
                      key: 2,
                      class: "pxc-facets__show-more",
                      onClick: (W) => R(x.attribute_id)
                    }, "Weniger anzeigen", 8, Fa)) : V("", !0)
                  ], 64)) : x.data_type === "Boolean" ? (_(), m("label", Oa, [
                    p("input", {
                      type: "checkbox",
                      checked: g(s).activeFilters[x.attribute_id] === "1",
                      onChange: (W) => S(x.attribute_id)
                    }, null, 40, Da),
                    p("span", null, L(x.label), 1)
                  ])) : x.data_type === "Decimal" || x.data_type === "Integer" ? (_(), m("div", Ra, [
                    p("div", ja, [
                      N[3] || (N[3] = p("label", null, "Von", -1)),
                      p("input", {
                        type: "number",
                        placeholder: x.min != null ? String(x.min) : "",
                        value: T(x.attribute_id).min,
                        onChange: (W) => J(x.attribute_id, W.target.value)
                      }, null, 40, Na)
                    ]),
                    N[5] || (N[5] = p("span", { class: "pxc-facets__range-sep" }, "–", -1)),
                    p("div", Va, [
                      N[4] || (N[4] = p("label", null, "Bis", -1)),
                      p("input", {
                        type: "number",
                        placeholder: x.max != null ? String(x.max) : "",
                        value: T(x.attribute_id).max,
                        onChange: (W) => se(x.attribute_id, W.target.value)
                      }, null, 40, Wa)
                    ]),
                    x.unit ? (_(), m("span", Ba, L(x.unit), 1)) : V("", !0)
                  ])) : V("", !0)
                ], 512), [
                  [Hr, !$(x.attribute_id)]
                ])
              ]))), 128))
            ])) : V("", !0)
          ]),
          p("div", Ua, [
            p("button", {
              class: "pxc-btn pxc-btn--outline",
              onClick: N[0] || (N[0] = (x) => {
                j(), a();
              })
            }, "Abbrechen"),
            p("button", {
              class: "pxc-btn pxc-btn--primary",
              onClick: h
            }, "Anwenden")
          ])
        ], 2)
      ]))
    ], 64)) : g(s).facets.length ? (_(), m("div", Ka, [
      p("div", qa, [
        N[6] || (N[6] = p("span", { class: "pxc-facets__title" }, "Filter", -1)),
        g(r).activeFilterCount.value > 0 ? (_(), m("button", {
          key: 0,
          class: "pxc-facets__clear-all",
          onClick: j
        }, "Alle zurücksetzen")) : V("", !0)
      ]),
      (_(!0), m(Z, null, ce(g(s).facets, (x) => (_(), m("div", {
        key: x.attribute_id,
        class: "pxc-facets__group"
      }, [
        p("button", {
          class: "pxc-facets__group-header",
          onClick: (W) => y(x.attribute_id)
        }, [
          p("span", {
            innerHTML: $(x.attribute_id) ? g(U).chevronRight : g(U).chevronDown
          }, null, 8, Ja),
          p("span", za, L(x.label), 1),
          ne(x.attribute_id) > 0 ? (_(), m("span", Za, L(ne(x.attribute_id)), 1)) : V("", !0)
        ], 8, Ga),
        qt(p("div", Ya, [
          x.data_type === "ValueList" || x.data_type === "Text" ? (_(), m(Z, { key: 0 }, [
            (x.values || []).length > 8 ? (_(), m("div", Qa, [
              qt(p("input", {
                "onUpdate:modelValue": (W) => l.value[x.attribute_id] = W,
                type: "text",
                placeholder: "Suchen...",
                class: "pxc-facets__search-input"
              }, null, 8, Xa), [
                [Kr, l.value[x.attribute_id]]
              ])
            ])) : V("", !0),
            (_(!0), m(Z, null, ce(E(x), (W) => (_(), m("label", {
              key: W.value_id || W.value,
              class: "pxc-facets__checkbox"
            }, [
              p("input", {
                type: "checkbox",
                checked: I(x.attribute_id, W.value_id || W.value),
                onChange: (he) => B(x.attribute_id, W.value_id || W.value)
              }, null, 40, eu),
              p("span", tu, L(W.value), 1),
              p("span", su, L(W.count), 1)
            ]))), 128)),
            ee(x) > 0 && !F(x.attribute_id) ? (_(), m("button", {
              key: 1,
              class: "pxc-facets__show-more",
              onClick: (W) => R(x.attribute_id)
            }, "Mehr anzeigen (+" + L(ee(x)) + ")", 9, nu)) : F(x.attribute_id) && (x.values || []).length > ys ? (_(), m("button", {
              key: 2,
              class: "pxc-facets__show-more",
              onClick: (W) => R(x.attribute_id)
            }, "Weniger anzeigen", 8, ru)) : V("", !0)
          ], 64)) : x.data_type === "Boolean" ? (_(), m("label", iu, [
            p("input", {
              type: "checkbox",
              checked: g(s).activeFilters[x.attribute_id] === "1",
              onChange: (W) => S(x.attribute_id)
            }, null, 40, ou),
            p("span", null, L(x.label), 1)
          ])) : x.data_type === "Decimal" || x.data_type === "Integer" ? (_(), m("div", lu, [
            p("div", cu, [
              N[7] || (N[7] = p("label", null, "Von", -1)),
              p("input", {
                type: "number",
                placeholder: x.min != null ? String(x.min) : "",
                value: T(x.attribute_id).min,
                onChange: (W) => J(x.attribute_id, W.target.value)
              }, null, 40, au)
            ]),
            N[9] || (N[9] = p("span", { class: "pxc-facets__range-sep" }, "–", -1)),
            p("div", uu, [
              N[8] || (N[8] = p("label", null, "Bis", -1)),
              p("input", {
                type: "number",
                placeholder: x.max != null ? String(x.max) : "",
                value: T(x.attribute_id).max,
                onChange: (W) => se(x.attribute_id, W.target.value)
              }, null, 40, fu)
            ]),
            x.unit ? (_(), m("span", du, L(x.unit), 1)) : V("", !0)
          ])) : V("", !0)
        ], 512), [
          [Hr, !$(x.attribute_id)]
        ])
      ]))), 128))
    ])) : V("", !0);
  }
}, pu = { class: "pxc-product-grid" }, _u = {
  key: 0,
  class: "pxc-product-grid__loading"
}, gu = {
  key: 1,
  class: "pxc-product-grid__empty"
}, mu = ["innerHTML"], vu = ["onClick"], yu = { class: "pxc-product-card__image" }, xu = ["src", "alt"], bu = {
  key: 1,
  class: "pxc-product-card__no-image"
}, wu = ["innerHTML"], ku = ["onClick", "title"], Cu = ["innerHTML"], $u = { class: "pxc-product-card__body" }, Tu = {
  key: 0,
  class: "pxc-product-card__category"
}, Su = { class: "pxc-product-card__name" }, Mu = {
  key: 1,
  class: "pxc-product-card__sku"
}, Pu = {
  key: 2,
  class: "pxc-product-card__attrs"
}, Lu = {
  key: 3,
  class: "pxc-product-card__price"
}, Au = {
  key: 3,
  class: "pxc-product-grid__overlay"
}, Eu = ["innerHTML"], Iu = {
  __name: "ProductGridWidget",
  setup(e) {
    const { state: t, actions: s, getters: n } = Le();
    Ft(() => {
      t.products.length === 0 && !t.loading && s.fetchProducts();
    }), De(() => t.locale, () => s.fetchProducts());
    function r(l, c) {
      return l ? new Intl.NumberFormat(t.locale === "de" ? "de-DE" : "en-US", {
        style: "currency",
        currency: c || "EUR"
      }).format(l) : null;
    }
    function i(l) {
      s.openDetail(l.id);
    }
    function o(l, c) {
      l.stopPropagation(), s.toggleWishlist(c);
    }
    return (l, c) => (_(), m("div", pu, [
      g(t).loading && g(t).products.length === 0 ? (_(), m("div", _u, [
        (_(), m(Z, null, ce(8, (u) => p("div", {
          key: u,
          class: "pxc-skeleton pxc-skeleton--card"
        })), 64))
      ])) : g(n).isEmpty.value ? (_(), m("div", gu, [
        p("span", {
          innerHTML: g(U).package,
          style: { width: "48px", height: "48px", opacity: "0.2" }
        }, null, 8, mu),
        c[0] || (c[0] = p("p", null, "Keine Produkte gefunden", -1))
      ])) : (_(), m("div", {
        key: 2,
        class: _e(["pxc-product-grid__grid", g(t).viewMode === "list" ? "pxc-product-grid__grid--list" : ""])
      }, [
        (_(!0), m(Z, null, ce(g(t).products, (u) => {
          var a;
          return _(), m("div", {
            key: u.id,
            class: "pxc-product-card",
            onClick: (h) => i(u)
          }, [
            p("div", yu, [
              u.image_url ? (_(), m("img", {
                key: 0,
                src: u.image_url,
                alt: u.name,
                loading: "lazy"
              }, null, 8, xu)) : (_(), m("div", bu, [
                p("span", {
                  innerHTML: g(U).package
                }, null, 8, wu)
              ])),
              p("button", {
                class: "pxc-product-card__wishlist",
                onClick: (h) => o(h, u.id),
                title: g(n).isInWishlist(u.id) ? "Von Merkliste entfernen" : "Zur Merkliste"
              }, [
                p("span", {
                  innerHTML: g(n).isInWishlist(u.id) ? g(U).heartFilled : g(U).heart,
                  class: _e({ "pxc-text-accent": g(n).isInWishlist(u.id) })
                }, null, 10, Cu)
              ], 8, ku)
            ]),
            p("div", $u, [
              u.category_path ? (_(), m("p", Tu, L(u.category_path), 1)) : V("", !0),
              p("h3", Su, L(u.primary_attribute_value && u.primary_attribute_value.trim() || u.name || u.sku || "–"), 1),
              u.sku ? (_(), m("p", Mu, L(u.sku), 1)) : V("", !0),
              (a = u.card_attributes) != null && a.length ? (_(), m("div", Pu, [
                (_(!0), m(Z, null, ce(u.card_attributes.slice(0, 3), (h, y) => (_(), m("span", { key: y }, L(h.value), 1))), 128))
              ])) : V("", !0),
              u.price ? (_(), m("div", Lu, L(r(u.price, u.currency)), 1)) : V("", !0)
            ])
          ], 8, vu);
        }), 128))
      ], 2)),
      g(t).loading && g(t).products.length > 0 ? (_(), m("div", Au, [
        p("span", {
          innerHTML: g(U).loader,
          style: { width: "32px", height: "32px" }
        }, null, 8, Eu)
      ])) : V("", !0)
    ]));
  }
}, Hu = {
  key: 0,
  class: "pxc-pagination"
}, Fu = { class: "pxc-pagination__info" }, Ou = { class: "pxc-pagination__buttons" }, Du = ["disabled"], Ru = ["innerHTML"], ju = {
  key: 0,
  disabled: "",
  class: "pxc-pagination__dots"
}, Nu = ["onClick"], Vu = ["disabled"], Wu = ["innerHTML"], Bu = {
  __name: "PaginationWidget",
  setup(e) {
    const { state: t, actions: s } = Le(), n = ge(() => {
      const { current_page: o, last_page: l } = t.meta;
      if (l <= 1) return [];
      const c = [], u = 5;
      let a = Math.max(1, o - Math.floor(u / 2)), h = Math.min(l, a + u - 1);
      a = Math.max(1, h - u + 1), a > 1 && (c.push(1), a > 2 && c.push("..."));
      for (let y = a; y <= h; y++) c.push(y);
      return h < l && (h < l - 1 && c.push("..."), c.push(l)), c;
    }), r = ge(() => {
      const { current_page: o, per_page: l, total: c } = t.meta;
      return {
        from: (o - 1) * l + 1,
        to: Math.min(o * l, c)
      };
    });
    function i(o) {
      typeof o == "number" && (s.setPage(o), s.fetchProducts(), window.scrollTo({ top: 0, behavior: "smooth" }));
    }
    return (o, l) => g(t).meta.last_page > 1 ? (_(), m("div", Hu, [
      p("p", Fu, L(r.value.from) + "–" + L(r.value.to) + " von " + L(g(t).meta.total), 1),
      p("div", Ou, [
        p("button", {
          disabled: g(t).meta.current_page <= 1,
          onClick: l[0] || (l[0] = (c) => i(g(t).meta.current_page - 1))
        }, [
          p("span", {
            innerHTML: g(U).chevronLeft
          }, null, 8, Ru)
        ], 8, Du),
        (_(!0), m(Z, null, ce(n.value, (c, u) => (_(), m(Z, { key: u }, [
          c === "..." ? (_(), m("button", ju, "...")) : (_(), m("button", {
            key: 1,
            class: _e({ "pxc-pagination__active": c === g(t).meta.current_page }),
            onClick: (a) => i(c)
          }, L(c), 11, Nu))
        ], 64))), 128)),
        p("button", {
          disabled: g(t).meta.current_page >= g(t).meta.last_page,
          onClick: l[1] || (l[1] = (c) => i(g(t).meta.current_page + 1))
        }, [
          p("span", {
            innerHTML: g(U).chevronRight
          }, null, 8, Wu)
        ], 8, Vu)
      ])
    ])) : V("", !0);
  }
}, Uu = { class: "pxc-toolbar" }, Ku = { class: "pxc-toolbar__count" }, qu = { class: "pxc-toolbar__actions" }, Gu = { class: "pxc-toolbar__sort" }, Ju = ["value"], zu = ["title"], Zu = ["innerHTML"], Yu = { class: "pxc-toolbar__view" }, Qu = ["innerHTML"], Xu = ["innerHTML"], ef = {
  __name: "ToolbarWidget",
  setup(e) {
    const { state: t, actions: s } = Le();
    function n() {
      const o = t.sort.order === "asc" ? "desc" : "asc";
      s.setSort(t.sort.field, o), s.fetchProducts();
    }
    function r(o) {
      s.setSort(o.target.value, t.sort.order), s.fetchProducts();
    }
    function i(o) {
      s.setViewMode(o);
    }
    return (o, l) => (_(), m("div", Uu, [
      p("span", Ku, [
        me(L(g(t).meta.total) + " Produkte ", 1),
        g(t).selectedCategoryName ? (_(), m(Z, { key: 0 }, [
          l[2] || (l[2] = me(" in ", -1)),
          p("strong", null, L(g(t).selectedCategoryName), 1)
        ], 64)) : V("", !0)
      ]),
      p("div", qu, [
        p("div", Gu, [
          p("select", {
            value: g(t).sort.field,
            onChange: r
          }, [...l[3] || (l[3] = [
            p("option", { value: "name" }, "Name", -1),
            p("option", { value: "sku" }, "Artikelnummer", -1),
            p("option", { value: "created_at" }, "Neu", -1),
            p("option", { value: "updated_at" }, "Aktualisiert", -1)
          ])], 40, Ju),
          p("button", {
            onClick: n,
            title: g(t).sort.order === "asc" ? "Aufsteigend" : "Absteigend"
          }, [
            p("span", {
              innerHTML: g(t).sort.order === "asc" ? g(U).sortAsc : g(U).sortDesc
            }, null, 8, Zu)
          ], 8, zu)
        ]),
        p("div", Yu, [
          p("button", {
            class: _e({ "pxc-toolbar__view--active": g(t).viewMode === "grid" }),
            onClick: l[0] || (l[0] = (c) => i("grid")),
            innerHTML: g(U).grid
          }, null, 10, Qu),
          p("button", {
            class: _e({ "pxc-toolbar__view--active": g(t).viewMode === "list" }),
            onClick: l[1] || (l[1] = (c) => i("list")),
            innerHTML: g(U).list
          }, null, 10, Xu)
        ])
      ])
    ]));
  }
}, tf = { class: "pxc-wishlist" }, sf = ["innerHTML"], nf = {
  key: 0,
  class: "pxc-wishlist__badge"
}, rf = { class: "pxc-wishlist__drawer-header" }, of = ["innerHTML"], lf = {
  key: 0,
  class: "pxc-wishlist__badge"
}, cf = ["innerHTML"], af = {
  key: 0,
  class: "pxc-wishlist__empty"
}, uf = ["innerHTML"], ff = {
  key: 1,
  class: "pxc-wishlist__items"
}, df = { class: "pxc-wishlist__item-image" }, hf = ["src", "alt"], pf = ["innerHTML"], _f = { class: "pxc-wishlist__item-info" }, gf = { class: "pxc-wishlist__item-name" }, mf = { class: "pxc-wishlist__item-sku" }, vf = {
  key: 0,
  class: "pxc-wishlist__item-price"
}, yf = ["onClick"], xf = ["innerHTML"], bf = {
  key: 0,
  class: "pxc-text-muted",
  style: { "text-align": "center", padding: "8px" }
}, wf = {
  key: 2,
  class: "pxc-wishlist__footer"
}, kf = ["disabled"], Cf = ["innerHTML"], $f = ["disabled"], Tf = ["innerHTML"], Sf = ["innerHTML"], Mf = ["innerHTML"], Pf = ["innerHTML"], Lf = {
  __name: "WishlistWidget",
  setup(e) {
    const { state: t, actions: s, getters: n } = Le(), r = /* @__PURE__ */ He(!1), i = /* @__PURE__ */ He(null), o = /* @__PURE__ */ He(!1);
    function l() {
      r.value = !0;
    }
    Ft(() => window.addEventListener("pxc:open-wishlist", l)), Gn(() => window.removeEventListener("pxc:open-wishlist", l));
    const c = ge(() => t.products.filter((B) => n.isInWishlist(B.id))), u = ge(() => {
      const B = new Set(t.products.map((I) => I.id));
      return t.wishlistIds.filter((I) => !B.has(I)).length;
    }), a = ge(
      () => t.settings.catalog_compare_enabled && n.wishlistCount.value >= 2 && n.wishlistCount.value <= (t.settings.catalog_compare_max_products || 3)
    );
    function h() {
      r.value = !r.value;
    }
    function y(B) {
      return B ? new Intl.NumberFormat(t.locale === "de" ? "de-DE" : "en-US", {
        style: "currency",
        currency: "EUR"
      }).format(B) : null;
    }
    async function $() {
      if (!i.value) {
        i.value = "pdf";
        try {
          await s.downloadWishlistPdf();
        } catch (B) {
          console.error("PDF export failed:", B);
        } finally {
          i.value = null;
        }
      }
    }
    async function R() {
      if (!i.value) {
        i.value = "excel";
        try {
          await s.downloadWishlistExcel();
        } catch (B) {
          console.error("Excel export failed:", B);
        } finally {
          i.value = null;
        }
      }
    }
    function F() {
      s.openCompare([...t.wishlistIds]);
    }
    async function Q() {
      const I = `${window.location.href.split("?")[0]}?wishlist=${t.wishlistIds.join(",")}`;
      try {
        await navigator.clipboard.writeText(I), o.value = !0, setTimeout(() => {
          o.value = !1;
        }, 2e3);
      } catch {
      }
    }
    return (B, I) => (_(), m("div", tf, [
      p("button", {
        class: "pxc-wishlist__toggle",
        onClick: h
      }, [
        p("span", {
          innerHTML: g(U).heart
        }, null, 8, sf),
        g(n).wishlistCount.value > 0 ? (_(), m("span", nf, L(g(n).wishlistCount.value), 1)) : V("", !0)
      ]),
      r.value ? (_(), m("div", {
        key: 0,
        class: "pxc-wishlist__overlay",
        onClick: h
      })) : V("", !0),
      p("div", {
        class: _e(["pxc-wishlist__drawer", { "pxc-wishlist__drawer--open": r.value }])
      }, [
        p("div", rf, [
          p("span", {
            innerHTML: g(U).heart,
            class: "pxc-text-accent"
          }, null, 8, of),
          I[1] || (I[1] = p("span", null, "Merkliste", -1)),
          g(n).wishlistCount.value ? (_(), m("span", lf, L(g(n).wishlistCount.value), 1)) : V("", !0),
          p("button", {
            class: "pxc-wishlist__close",
            onClick: h,
            innerHTML: g(U).x
          }, null, 8, cf)
        ]),
        g(n).wishlistCount.value === 0 ? (_(), m("div", af, [
          p("span", {
            innerHTML: g(U).heart,
            style: { width: "40px", height: "40px", opacity: "0.15" }
          }, null, 8, uf),
          I[2] || (I[2] = p("p", null, "Merkliste ist leer", -1)),
          I[3] || (I[3] = p("p", { class: "pxc-text-muted" }, "Klicken Sie auf das Herz-Symbol bei einem Produkt", -1))
        ])) : (_(), m("div", ff, [
          (_(!0), m(Z, null, ce(c.value, (S) => (_(), m("div", {
            key: S.id,
            class: "pxc-wishlist__item"
          }, [
            p("div", df, [
              S.image_url ? (_(), m("img", {
                key: 0,
                src: S.image_url,
                alt: S.name
              }, null, 8, hf)) : (_(), m("span", {
                key: 1,
                innerHTML: g(U).package
              }, null, 8, pf))
            ]),
            p("div", _f, [
              p("p", gf, L(S.name), 1),
              p("p", mf, L(S.sku), 1),
              S.price ? (_(), m("p", vf, L(y(S.price)), 1)) : V("", !0)
            ]),
            p("button", {
              class: "pxc-wishlist__item-remove",
              onClick: (T) => g(s).toggleWishlist(S.id)
            }, [
              p("span", {
                innerHTML: g(U).trash
              }, null, 8, xf)
            ], 8, yf)
          ]))), 128)),
          u.value > 0 ? (_(), m("div", bf, " + " + L(u.value) + " weitere Produkte ", 1)) : V("", !0)
        ])),
        g(n).wishlistCount.value > 0 ? (_(), m("div", wf, [
          g(t).settings.catalog_pdf_enabled ? (_(), m("button", {
            key: 0,
            class: "pxc-btn pxc-btn--primary",
            onClick: $,
            disabled: !!i.value
          }, [
            p("span", {
              innerHTML: g(U).fileDown
            }, null, 8, Cf),
            me(" " + L(i.value === "pdf" ? "Exportiere..." : "Als PDF"), 1)
          ], 8, kf)) : V("", !0),
          g(t).settings.catalog_excel_export_enabled ? (_(), m("button", {
            key: 1,
            class: "pxc-btn pxc-btn--outline",
            onClick: R,
            disabled: !!i.value
          }, [
            p("span", {
              innerHTML: g(U).sheet
            }, null, 8, Tf),
            me(" " + L(i.value === "excel" ? "Exportiere..." : "Excel-Export"), 1)
          ], 8, $f)) : V("", !0),
          a.value ? (_(), m("button", {
            key: 2,
            class: "pxc-btn pxc-btn--outline",
            onClick: F
          }, [
            p("span", {
              innerHTML: g(U).compare
            }, null, 8, Sf),
            me(" Vergleichen (" + L(g(n).wishlistCount.value) + ") ", 1)
          ])) : V("", !0),
          g(t).settings.catalog_share_wishlist_enabled ? (_(), m("button", {
            key: 3,
            class: "pxc-btn pxc-btn--ghost",
            onClick: Q
          }, [
            p("span", {
              innerHTML: o.value ? g(U).check : g(U).share
            }, null, 8, Mf),
            me(" " + L(o.value ? "Link kopiert!" : "Teilen"), 1)
          ])) : V("", !0),
          p("button", {
            class: "pxc-btn pxc-btn--danger",
            onClick: I[0] || (I[0] = (S) => g(s).clearWishlist())
          }, [
            p("span", {
              innerHTML: g(U).trash
            }, null, 8, Pf),
            I[4] || (I[4] = me(" Leeren ", -1))
          ])
        ])) : V("", !0)
      ], 2)
    ]));
  }
}, Af = ["innerHTML"], Ef = {
  key: 0,
  class: "pxc-wishlist-btn__badge"
}, If = {
  __name: "WishlistButtonWidget",
  setup(e) {
    const { state: t, getters: s } = Le();
    function n() {
      window.dispatchEvent(new CustomEvent("pxc:open-wishlist"));
    }
    return (r, i) => (_(), m("button", {
      class: "pxc-wishlist-btn",
      onClick: n
    }, [
      p("span", {
        innerHTML: g(U).heart
      }, null, 8, Af),
      i[0] || (i[0] = p("span", null, "Merkliste", -1)),
      g(s).wishlistCount.value > 0 ? (_(), m("span", Ef, L(g(s).wishlistCount.value), 1)) : V("", !0)
    ]));
  }
}, Hf = { class: "pxc-detail-modal" }, Ff = ["innerHTML"], Of = {
  key: 0,
  class: "pxc-detail-modal__loading"
}, Df = ["innerHTML"], Rf = {
  key: 1,
  class: "pxc-detail"
}, jf = { class: "pxc-detail__layout" }, Nf = { class: "pxc-detail__gallery" }, Vf = { class: "pxc-detail__main-image" }, Wf = ["src", "alt"], Bf = {
  key: 1,
  class: "pxc-detail__no-image"
}, Uf = ["innerHTML"], Kf = ["innerHTML"], qf = ["innerHTML"], Gf = {
  key: 0,
  class: "pxc-detail__thumbs"
}, Jf = ["onClick"], zf = ["src", "alt"], Zf = { class: "pxc-detail__info" }, Yf = {
  key: 0,
  class: "pxc-detail__breadcrumb"
}, Qf = { class: "pxc-detail__title" }, Xf = { class: "pxc-detail__meta" }, ed = { key: 0 }, td = { key: 1 }, sd = {
  key: 1,
  class: "pxc-detail__description"
}, nd = {
  key: 2,
  class: "pxc-detail__prices"
}, rd = { class: "pxc-detail__price-label" }, id = { class: "pxc-detail__price-value" }, od = { class: "pxc-detail__actions" }, ld = ["innerHTML"], cd = ["innerHTML"], ad = { class: "pxc-detail__tabs" }, ud = ["onClick"], fd = {
  key: 3,
  class: "pxc-detail__tab-content"
}, dd = {
  key: 0,
  class: "pxc-detail__table"
}, hd = { class: "pxc-detail__table-label" }, pd = { class: "pxc-detail__table-value" }, _d = ["href"], gd = ["href"], md = {
  key: 3,
  class: "pxc-text-muted"
}, vd = {
  key: 1,
  class: "pxc-detail__empty"
}, yd = {
  key: 4,
  class: "pxc-detail__tab-content"
}, xd = {
  key: 0,
  class: "pxc-detail__documents"
}, bd = ["href"], wd = ["innerHTML"], kd = { class: "pxc-detail__doc-info" }, Cd = { class: "pxc-detail__doc-name" }, $d = { class: "pxc-detail__doc-type" }, Td = {
  key: 1,
  class: "pxc-detail__empty"
}, Sd = {
  key: 5,
  class: "pxc-detail__tab-content"
}, Md = { class: "pxc-detail__relation-type" }, Pd = { class: "pxc-detail__relation-items" }, Ld = ["onClick"], Ad = { class: "pxc-detail__relation-img" }, Ed = ["src", "alt"], Id = ["innerHTML"], Hd = { class: "pxc-detail__relation-info" }, Fd = { class: "pxc-detail__relation-name" }, Od = {
  key: 0,
  class: "pxc-detail__relation-sku"
}, Dd = {
  key: 2,
  class: "pxc-detail-modal__error"
}, Rd = {
  __name: "ProductDetailWidget",
  setup(e) {
    const { state: t, actions: s, getters: n } = Le(), r = /* @__PURE__ */ He(0), i = /* @__PURE__ */ He("attributes");
    De(() => t.detailOpen, (S) => {
      document.body.style.overflow = S ? "hidden" : "", S && (i.value = "attributes", r.value = 0);
    });
    const o = ge(() => {
      var S;
      return (S = t.currentProduct) != null && S.media ? t.currentProduct.media.filter((T) => T.media_type === "image") : [];
    }), l = ge(() => {
      var S;
      return (S = t.currentProduct) != null && S.media ? t.currentProduct.media.filter((T) => T.media_type !== "image") : [];
    }), c = ge(() => o.value[r.value]);
    De(() => t.currentProduct, () => {
      r.value = 0;
    });
    const u = ge(() => {
      var Y;
      const S = (Y = t.currentProduct) == null ? void 0 : Y.attributes;
      if (!(S != null && S.length)) return [];
      const T = /* @__PURE__ */ new Set();
      return S.forEach((J) => {
        J.parent_attribute_id && T.add(J.attribute_id);
      }), S.filter((J) => !J.parent_attribute_id || !T.has(J.attribute_id));
    }), a = ge(() => {
      var Y;
      const S = (Y = t.currentProduct) == null ? void 0 : Y.relations;
      if (!(S != null && S.length)) return [];
      const T = {};
      for (const J of S) {
        const se = J.relation_type_id || "default";
        T[se] || (T[se] = {
          type_id: se,
          type_name: J.relation_type || (t.locale === "de" ? "Verwandte Produkte" : "Related Products"),
          products: []
        }), T[se].products.push({
          id: J.target_product_id,
          name: J.name,
          sku: J.sku,
          image_url: J.image_url ? Mn(J.image_url) : null
        });
      }
      return Object.values(T);
    }), h = ge(() => a.value.length > 0), y = ge(() => l.value.length > 0), $ = ge(() => {
      const S = [{ key: "attributes", label: t.locale === "de" ? "Eigenschaften" : "Attributes" }];
      return y.value && S.push({ key: "media", label: t.locale === "de" ? "Dokumente" : "Documents" }), h.value && S.push({ key: "relations", label: t.locale === "de" ? "Beziehungen" : "Relations" }), S;
    });
    function R() {
      r.value = r.value > 0 ? r.value - 1 : o.value.length - 1;
    }
    function F() {
      r.value = r.value < o.value.length - 1 ? r.value + 1 : 0;
    }
    function Q(S, T) {
      return S ? new Intl.NumberFormat(t.locale === "de" ? "de-DE" : "en-US", {
        style: "currency",
        currency: T || "EUR"
      }).format(S) : null;
    }
    async function B() {
      t.currentProduct && await s.downloadProductPdf(t.currentProduct.id);
    }
    function I(S) {
      return S != null && S.includes("pdf") ? U.fileDown : S != null && S.includes("sheet") || S != null && S.includes("excel") ? U.sheet : U.fileDown;
    }
    return (S, T) => (_(), zs(qn, { to: "body" }, [
      be(Qn, { name: "pxc-fade" }, {
        default: Us(() => {
          var Y, J, se;
          return [
            g(t).detailOpen ? (_(), m("div", {
              key: 0,
              class: "pxc-detail-overlay",
              onClick: T[2] || (T[2] = ao((H) => g(s).closeDetail(), ["self"]))
            }, [
              p("div", Hf, [
                p("button", {
                  class: "pxc-detail-modal__close",
                  onClick: T[0] || (T[0] = (H) => g(s).closeDetail()),
                  innerHTML: g(U).x
                }, null, 8, Ff),
                g(t).productLoading ? (_(), m("div", Of, [
                  p("span", {
                    innerHTML: g(U).loader,
                    style: { width: "32px", height: "32px" }
                  }, null, 8, Df),
                  p("p", null, L(g(t).locale === "de" ? "Lade Produktdetails…" : "Loading product…"), 1)
                ])) : g(t).currentProduct ? (_(), m("div", Rf, [
                  p("div", jf, [
                    p("div", Nf, [
                      p("div", Vf, [
                        c.value ? (_(), m("img", {
                          key: 0,
                          src: c.value.url,
                          alt: c.value.alt || ""
                        }, null, 8, Wf)) : (_(), m("div", Bf, [
                          p("span", {
                            innerHTML: g(U).package,
                            style: { width: "64px", height: "64px", opacity: "0.1" }
                          }, null, 8, Uf)
                        ])),
                        o.value.length > 1 ? (_(), m(Z, { key: 2 }, [
                          p("button", {
                            class: "pxc-detail__nav pxc-detail__nav--prev",
                            onClick: R,
                            innerHTML: g(U).chevronLeft
                          }, null, 8, Kf),
                          p("button", {
                            class: "pxc-detail__nav pxc-detail__nav--next",
                            onClick: F,
                            innerHTML: g(U).chevronRight
                          }, null, 8, qf)
                        ], 64)) : V("", !0)
                      ]),
                      o.value.length > 1 ? (_(), m("div", Gf, [
                        (_(!0), m(Z, null, ce(o.value, (H, E) => (_(), m("button", {
                          key: H.url,
                          class: _e(["pxc-detail__thumb", { "pxc-detail__thumb--active": E === r.value }]),
                          onClick: (ee) => r.value = E
                        }, [
                          p("img", {
                            src: H.url,
                            alt: H.alt || ""
                          }, null, 8, zf)
                        ], 10, Jf))), 128))
                      ])) : V("", !0)
                    ]),
                    p("div", Zf, [
                      (Y = g(t).currentProduct.category_breadcrumb) != null && Y.length ? (_(), m("p", Yf, [
                        (_(!0), m(Z, null, ce(g(t).currentProduct.category_breadcrumb, (H, E) => (_(), m("span", { key: E }, [
                          me(L(H.name), 1),
                          E < g(t).currentProduct.category_breadcrumb.length - 1 ? (_(), m(Z, { key: 0 }, [
                            me(" / ")
                          ], 64)) : V("", !0)
                        ]))), 128))
                      ])) : V("", !0),
                      p("h2", Qf, L(g(t).currentProduct.name), 1),
                      p("div", Xf, [
                        g(t).currentProduct.sku ? (_(), m("span", ed, "SKU: " + L(g(t).currentProduct.sku), 1)) : V("", !0),
                        g(t).currentProduct.ean ? (_(), m("span", td, "EAN: " + L(g(t).currentProduct.ean), 1)) : V("", !0)
                      ]),
                      (J = g(t).currentProduct.description_attributes) != null && J.length ? (_(), m("div", sd, [
                        (_(!0), m(Z, null, ce(g(t).currentProduct.description_attributes, (H) => (_(), m("div", {
                          key: H.attribute_id,
                          class: _e("pxc-detail__desc-" + (H.typography || "base"))
                        }, L(H.value), 3))), 128))
                      ])) : V("", !0),
                      (se = g(t).currentProduct.prices) != null && se.length ? (_(), m("div", nd, [
                        (_(!0), m(Z, null, ce(g(t).currentProduct.prices, (H, E) => (_(), m("div", {
                          key: E,
                          class: "pxc-detail__price"
                        }, [
                          p("span", rd, L(H.type_name || "Preis"), 1),
                          p("span", id, L(Q(H.amount, H.currency)), 1)
                        ]))), 128))
                      ])) : V("", !0),
                      p("div", od, [
                        p("button", {
                          class: _e(["pxc-btn", g(n).isInWishlist(g(t).currentProduct.id) ? "pxc-btn--accent" : "pxc-btn--outline"]),
                          onClick: T[1] || (T[1] = (H) => g(s).toggleWishlist(g(t).currentProduct.id))
                        }, [
                          p("span", {
                            innerHTML: g(n).isInWishlist(g(t).currentProduct.id) ? g(U).heartFilled : g(U).heart
                          }, null, 8, ld),
                          me(" " + L(g(n).isInWishlist(g(t).currentProduct.id) ? g(t).locale === "de" ? "Auf Merkliste" : "On Wishlist" : g(t).locale === "de" ? "Zur Merkliste" : "Add to Wishlist"), 1)
                        ], 2),
                        g(t).settings.catalog_pdf_enabled ? (_(), m("button", {
                          key: 0,
                          class: "pxc-btn pxc-btn--outline",
                          onClick: B
                        }, [
                          p("span", {
                            innerHTML: g(U).fileDown
                          }, null, 8, cd),
                          T[3] || (T[3] = me(" PDF ", -1))
                        ])) : V("", !0)
                      ]),
                      p("div", ad, [
                        (_(!0), m(Z, null, ce($.value, (H) => (_(), m("button", {
                          key: H.key,
                          class: _e(["pxc-detail__tab", { "pxc-detail__tab--active": i.value === H.key }]),
                          onClick: (E) => i.value = H.key
                        }, L(H.label), 11, ud))), 128))
                      ]),
                      i.value === "attributes" ? (_(), m("div", fd, [
                        u.value.length ? (_(), m("table", dd, [
                          p("tbody", null, [
                            (_(!0), m(Z, null, ce(u.value, (H) => (_(), m("tr", {
                              key: H.attribute_id
                            }, [
                              p("td", hd, L(H.label), 1),
                              p("td", pd, [
                                H.link_data ? (_(), m("a", {
                                  key: 0,
                                  href: H.link_data.url,
                                  target: "_blank",
                                  rel: "noopener"
                                }, L(H.link_data.title || H.link_data.url), 9, _d)) : H.data_type === "Hyperlink" ? (_(), m("a", {
                                  key: 1,
                                  href: H.value,
                                  target: "_blank",
                                  rel: "noopener"
                                }, L(H.value), 9, gd)) : (_(), m(Z, { key: 2 }, [
                                  me(L(H.value || "—"), 1)
                                ], 64)),
                                H.unit ? (_(), m("span", md, L(H.unit), 1)) : V("", !0)
                              ])
                            ]))), 128))
                          ])
                        ])) : (_(), m("p", vd, L(g(t).locale === "de" ? "Keine Eigenschaften vorhanden." : "No attributes available."), 1))
                      ])) : V("", !0),
                      i.value === "media" ? (_(), m("div", yd, [
                        l.value.length ? (_(), m("div", xd, [
                          (_(!0), m(Z, null, ce(l.value, (H) => (_(), m("a", {
                            key: H.file_name,
                            href: H.url,
                            target: "_blank",
                            rel: "noopener",
                            class: "pxc-detail__doc-item"
                          }, [
                            p("span", {
                              class: "pxc-detail__doc-icon",
                              innerHTML: I(H.mime_type)
                            }, null, 8, wd),
                            p("div", kd, [
                              p("span", Cd, L(H.description || H.file_name), 1),
                              p("span", $d, L(H.mime_type), 1)
                            ])
                          ], 8, bd))), 128))
                        ])) : (_(), m("p", Td, L(g(t).locale === "de" ? "Keine Dokumente vorhanden." : "No documents available."), 1))
                      ])) : V("", !0),
                      i.value === "relations" ? (_(), m("div", Sd, [
                        (_(!0), m(Z, null, ce(a.value, (H) => (_(), m("div", {
                          key: H.type_id,
                          class: "pxc-detail__relation-group"
                        }, [
                          p("h4", Md, L(H.type_name), 1),
                          p("div", Pd, [
                            (_(!0), m(Z, null, ce(H.products, (E) => (_(), m("div", {
                              key: E.id,
                              class: "pxc-detail__relation-card",
                              onClick: (ee) => g(s).openDetail(E.id)
                            }, [
                              p("div", Ad, [
                                E.image_url ? (_(), m("img", {
                                  key: 0,
                                  src: E.image_url,
                                  alt: E.name
                                }, null, 8, Ed)) : (_(), m("span", {
                                  key: 1,
                                  innerHTML: g(U).package,
                                  class: "pxc-detail__relation-placeholder"
                                }, null, 8, Id))
                              ]),
                              p("div", Hd, [
                                p("p", Fd, L(E.name), 1),
                                E.sku ? (_(), m("span", Od, L(E.sku), 1)) : V("", !0)
                              ])
                            ], 8, Ld))), 128))
                          ])
                        ]))), 128))
                      ])) : V("", !0)
                    ])
                  ])
                ])) : g(t).error ? (_(), m("div", Dd, [
                  p("p", null, L(g(t).error), 1)
                ])) : V("", !0)
              ])
            ])) : V("", !0)
          ];
        }),
        _: 1
      })
    ]));
  }
}, jd = { class: "pxc-compare-modal" }, Nd = { class: "pxc-compare-modal__header" }, Vd = ["innerHTML"], Wd = {
  key: 0,
  class: "pxc-text-muted"
}, Bd = { class: "pxc-compare-modal__filter" }, Ud = ["innerHTML"], Kd = { class: "pxc-compare-modal__body" }, qd = {
  key: 0,
  class: "pxc-compare-modal__loading"
}, Gd = {
  key: 1,
  class: "pxc-compare-table"
}, Jd = { class: "pxc-text-muted" }, zd = { key: 0 }, Zd = ["colspan"], Yd = {
  __name: "CompareWidget",
  setup(e) {
    const { state: t, actions: s } = Le(), n = /* @__PURE__ */ He(!1);
    De(() => t.compareOpen, (i) => {
      document.body.style.overflow = i ? "hidden" : "";
    });
    const r = ge(() => {
      var i;
      return (i = t.compareData) != null && i.rows ? n.value ? t.compareData.rows.filter((o) => o.is_different) : t.compareData.rows : [];
    });
    return (i, o) => (_(), zs(qn, { to: "body" }, [
      be(Qn, { name: "pxc-fade" }, {
        default: Us(() => {
          var l, c;
          return [
            g(t).compareOpen ? (_(), m("div", {
              key: 0,
              class: "pxc-compare-overlay",
              onClick: o[2] || (o[2] = ao((u) => g(s).closeCompare(), ["self"]))
            }, [
              p("div", jd, [
                p("div", Nd, [
                  p("span", {
                    innerHTML: g(U).compare
                  }, null, 8, Vd),
                  o[4] || (o[4] = p("span", null, "Produktvergleich", -1)),
                  g(t).compareData ? (_(), m("span", Wd, L(g(t).compareData.total_differences) + " Unterschiede von " + L(g(t).compareData.total_attributes) + " Feldern ", 1)) : V("", !0),
                  o[5] || (o[5] = p("div", { style: { flex: "1" } }, null, -1)),
                  p("label", Bd, [
                    qt(p("input", {
                      type: "checkbox",
                      "onUpdate:modelValue": o[0] || (o[0] = (u) => n.value = u)
                    }, null, 512), [
                      [Hc, n.value]
                    ]),
                    o[3] || (o[3] = me(" Nur Unterschiede ", -1))
                  ]),
                  p("button", {
                    class: "pxc-btn pxc-btn--ghost",
                    onClick: o[1] || (o[1] = (u) => g(s).closeCompare()),
                    innerHTML: g(U).x
                  }, null, 8, Ud)
                ]),
                p("div", Kd, [
                  g(t).compareLoading ? (_(), m("div", qd, [
                    (_(), m(Z, null, ce(8, (u) => p("div", {
                      key: u,
                      class: "pxc-skeleton",
                      style: { height: "32px", "margin-bottom": "4px" }
                    })), 64))
                  ])) : g(t).compareData ? (_(), m("table", Gd, [
                    p("thead", null, [
                      p("tr", null, [
                        o[6] || (o[6] = p("th", null, "Attribut", -1)),
                        (_(!0), m(Z, null, ce(g(t).compareData.products, (u) => (_(), m("th", {
                          key: u.id
                        }, [
                          me(L(u.sku) + " ", 1),
                          p("span", Jd, L(u.name), 1)
                        ]))), 128))
                      ])
                    ]),
                    p("tbody", null, [
                      (_(!0), m(Z, null, ce(r.value, (u, a) => (_(), m("tr", {
                        key: a,
                        class: _e({ "pxc-compare-table__diff": u.is_different })
                      }, [
                        p("td", null, L(u.attribute_name), 1),
                        (_(!0), m(Z, null, ce(u.values, (h, y) => (_(), m("td", { key: y }, L(h ?? "—"), 1))), 128))
                      ], 2))), 128)),
                      r.value.length === 0 ? (_(), m("tr", zd, [
                        p("td", {
                          colspan: 1 + (((c = (l = g(t).compareData) == null ? void 0 : l.products) == null ? void 0 : c.length) || 0),
                          style: { "text-align": "center", padding: "32px" }
                        }, L(n.value ? "Keine Unterschiede" : "Keine Attribute"), 9, Zd)
                      ])) : V("", !0)
                    ])
                  ])) : V("", !0)
                ])
              ])
            ])) : V("", !0)
          ];
        }),
        _: 1
      })
    ]));
  }
}, Qd = { class: "pxc-locale" }, Xd = ["innerHTML"], eh = {
  __name: "LocaleWidget",
  setup(e) {
    const { state: t, actions: s } = Le();
    function n(r) {
      s.setLocale(r), s.fetchProducts(), s.fetchCategories();
    }
    return (r, i) => (_(), m("div", Qd, [
      p("span", {
        innerHTML: g(U).globe
      }, null, 8, Xd),
      p("button", {
        class: _e(["pxc-locale__btn", { "pxc-locale__btn--active": g(t).locale === "de" }]),
        onClick: i[0] || (i[0] = (o) => n("de"))
      }, "DE", 2),
      p("button", {
        class: _e(["pxc-locale__btn", { "pxc-locale__btn--active": g(t).locale === "en" }]),
        onClick: i[1] || (i[1] = (o) => n("en"))
      }, "EN", 2)
    ]));
  }
}, th = {
  key: 0,
  class: "pxc-active-filters"
}, sh = ["onClick", "innerHTML"], nh = {
  __name: "ActiveFiltersWidget",
  setup(e) {
    const { state: t, actions: s, getters: n } = Le(), r = ge(() => {
      const l = [];
      t.selectedCategoryName && l.push({ type: "category", label: t.selectedCategoryName }), t.search && l.push({ type: "search", label: `"${t.search}"` });
      for (const [c, u] of Object.entries(t.activeFilters)) {
        const a = t.facets.find((y) => String(y.attribute_id) === String(c)), h = a ? a.label : `Filter ${c}`;
        l.push({ type: "filter", attrId: c, label: `${h}: ${u}` });
      }
      return l;
    });
    function i(l) {
      l.type === "category" ? s.clearCategory() : l.type === "search" ? s.setSearch("") : l.type === "filter" && s.clearFilter(l.attrId), s.fetchProducts();
    }
    function o() {
      s.setSearch(""), s.clearCategory(), s.clearAllFilters(), s.fetchProducts();
    }
    return (l, c) => r.value.length > 0 ? (_(), m("div", th, [
      (_(!0), m(Z, null, ce(r.value, (u, a) => (_(), m("span", {
        key: a,
        class: "pxc-active-filters__chip"
      }, [
        me(L(u.label) + " ", 1),
        p("button", {
          onClick: (h) => i(u),
          innerHTML: g(U).x
        }, null, 8, sh)
      ]))), 128)),
      r.value.length > 1 ? (_(), m("button", {
        key: 0,
        class: "pxc-active-filters__clear",
        onClick: o
      }, " Alle löschen ")) : V("", !0)
    ])) : V("", !0);
  }
}, Pn = {
  search: Qc,
  categories: ma,
  facets: hu,
  "product-grid": Iu,
  pagination: Bu,
  toolbar: ef,
  wishlist: Lf,
  "wishlist-button": If,
  "product-detail": Rd,
  compare: Yd,
  locale: eh,
  "active-filters": nh
}, Ln = [];
function An() {
  document.querySelectorAll("[data-catalog]").forEach((t) => {
    if (t.__pxc_mounted) return;
    const s = t.getAttribute("data-catalog"), n = Pn[s];
    if (!n) {
      console.warn(`[PublixxCatalog] Unknown widget: "${s}". Available: ${Object.keys(Pn).join(", ")}`);
      return;
    }
    const r = {};
    for (const o of t.attributes)
      if (o.name.startsWith("data-") && o.name !== "data-catalog") {
        const l = o.name.slice(5).replace(/-([a-z])/g, (c, u) => u.toUpperCase());
        r[l] = o.value;
      }
    const i = Nc({
      render() {
        return ro(n, r);
      }
    });
    i.mount(t), t.__pxc_mounted = !0, Ln.push({ el: t, app: i });
  });
}
function rh() {
  Ln.forEach(({ app: e }) => e.unmount()), Ln.length = 0;
}
async function ih(e = {}) {
  Kc({
    baseUrl: e.api || e.baseUrl || "/api/v1",
    token: e.token,
    timeout: e.timeout
  });
  const { state: t, actions: s } = Le();
  e.locale && (t.locale = e.locale), e.perPage && (t.meta.per_page = e.perPage), await s.fetchSettings(), s.importWishlistFromUrl(), e.autoMount !== !1 && (document.readyState === "loading" ? document.addEventListener("DOMContentLoaded", An) : An());
}
const oh = {
  init: ih,
  mount: An,
  destroy: rh,
  store: Le,
  widgets: Pn,
  version: "1.0.0"
};
typeof window < "u" && (window.PublixxCatalog = oh);
export {
  oh as default,
  rh as destroy,
  ih as init,
  An as mount,
  Le as useStore
};
