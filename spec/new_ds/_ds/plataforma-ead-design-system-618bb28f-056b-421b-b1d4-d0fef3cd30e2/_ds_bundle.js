/* @ds-bundle: {"format":4,"namespace":"PlataformaEADDesignSystem_618bb2","components":[{"name":"Avatar","sourcePath":"components/actions/Avatar.jsx"},{"name":"Badge","sourcePath":"components/actions/Badge.jsx"},{"name":"Button","sourcePath":"components/actions/Button.jsx"},{"name":"Chip","sourcePath":"components/actions/Chip.jsx"},{"name":"DeleteButton","sourcePath":"components/actions/DeleteButton.jsx"},{"name":"Fab","sourcePath":"components/actions/Fab.jsx"},{"name":"ICON_PATHS","sourcePath":"components/actions/Icon.jsx"},{"name":"Icon","sourcePath":"components/actions/Icon.jsx"},{"name":"HelpButton","sourcePath":"components/app/HelpButton.jsx"},{"name":"NotificationsBell","sourcePath":"components/app/NotificationsBell.jsx"},{"name":"Card","sourcePath":"components/data/Card.jsx"},{"name":"DataTable","sourcePath":"components/data/DataTable.jsx"},{"name":"EmptyState","sourcePath":"components/data/EmptyState.jsx"},{"name":"Pagination","sourcePath":"components/data/Pagination.jsx"},{"name":"Progress","sourcePath":"components/data/Progress.jsx"},{"name":"StatCard","sourcePath":"components/data/StatCard.jsx"},{"name":"Table","sourcePath":"components/data/Table.jsx"},{"name":"Tabs","sourcePath":"components/data/Tabs.jsx"},{"name":"Alert","sourcePath":"components/feedback/Alert.jsx"},{"name":"ConfirmModal","sourcePath":"components/feedback/ConfirmModal.jsx"},{"name":"Modal","sourcePath":"components/feedback/Modal.jsx"},{"name":"Checkbox","sourcePath":"components/forms/Checkbox.jsx"},{"name":"FieldStack","sourcePath":"components/forms/FieldStack.jsx"},{"name":"FilterBar","sourcePath":"components/forms/FilterBar.jsx"},{"name":"FormActions","sourcePath":"components/forms/FormActions.jsx"},{"name":"Input","sourcePath":"components/forms/Input.jsx"},{"name":"Select","sourcePath":"components/forms/Select.jsx"},{"name":"Switch","sourcePath":"components/forms/Switch.jsx"},{"name":"Textarea","sourcePath":"components/forms/Textarea.jsx"},{"name":"Footer","sourcePath":"components/layout/Footer.jsx"},{"name":"GuestPanel","sourcePath":"components/layout/GuestPanel.jsx"},{"name":"PageHeader","sourcePath":"components/layout/PageHeader.jsx"},{"name":"DEFAULT_SECTIONS","sourcePath":"components/layout/Sidebar.jsx"},{"name":"Sidebar","sourcePath":"components/layout/Sidebar.jsx"},{"name":"Topbar","sourcePath":"components/layout/Topbar.jsx"}],"sourceHashes":{"components/actions/Avatar.jsx":"a7f09d796442","components/actions/Badge.jsx":"0844aa759357","components/actions/Button.jsx":"41a7a45611f4","components/actions/Chip.jsx":"17bbe8bb80f4","components/actions/DeleteButton.jsx":"1dd8a983c604","components/actions/Fab.jsx":"fcf2030330b9","components/actions/Icon.jsx":"a61d49414182","components/app/HelpButton.jsx":"23c0b09a22c7","components/app/NotificationsBell.jsx":"12ec0d21c683","components/data/Card.jsx":"7a786171cd0d","components/data/DataTable.jsx":"1fd28b824601","components/data/EmptyState.jsx":"dfd64e89e959","components/data/Pagination.jsx":"fe5118016410","components/data/Progress.jsx":"2d4f25363275","components/data/StatCard.jsx":"af596a3dc5dd","components/data/Table.jsx":"24186f2af07a","components/data/Tabs.jsx":"810e08a496ea","components/feedback/Alert.jsx":"036a471e9070","components/feedback/ConfirmModal.jsx":"05f0f9d3824e","components/feedback/Modal.jsx":"ab52a406bcea","components/forms/Checkbox.jsx":"ff2d6880c5f9","components/forms/FieldStack.jsx":"8b18ca9af638","components/forms/FilterBar.jsx":"410571f630af","components/forms/FormActions.jsx":"8a88dedaea54","components/forms/Input.jsx":"8aa214c39a90","components/forms/Select.jsx":"f8a650b50527","components/forms/Switch.jsx":"9ec18aaa53b0","components/forms/Textarea.jsx":"5b1800051dc5","components/layout/Footer.jsx":"6f05484b3df7","components/layout/GuestPanel.jsx":"74a46e64f82a","components/layout/PageHeader.jsx":"42ec854aa251","components/layout/Sidebar.jsx":"90c2d9897a20","components/layout/Topbar.jsx":"34b59f03d0ea","ui_kits/lms_app/data.js":"807f770ac8c5","ui_kits/lms_app/screens.jsx":"635ea3ef23e1","ui_kits/public_site/screens.jsx":"5958cc18558f"},"inlinedExternals":[],"unexposedExports":[]} */

(() => {

const __ds_ns = (window.PlataformaEADDesignSystem_618bb2 = window.PlataformaEADDesignSystem_618bb2 || {});

const __ds_scope = {};

(__ds_ns.__errors = __ds_ns.__errors || []);

// components/actions/Avatar.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function Avatar({
  name = '',
  size = 'md',
  tone = 'mint',
  className = '',
  ...rest
}) {
  const initials = name.split(' ').filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase() || '?';
  const tones = {
    mint: {
      background: 'var(--mint-100)',
      color: 'var(--mint-800)'
    },
    blue: {
      background: 'var(--blue-100)',
      color: 'var(--blue-800)'
    },
    sky: {
      background: 'var(--sky-100)',
      color: 'var(--sky-800)'
    },
    neutral: {
      background: 'var(--surface-alt)',
      color: 'var(--grey-700)'
    }
  };
  return /*#__PURE__*/React.createElement("div", _extends({
    className: ['ds-avatar', size === 'sm' ? 'ds-avatar-sm' : size === 'lg' ? 'ds-avatar-lg' : '', className].filter(Boolean).join(' '),
    style: tones[tone] || tones.mint,
    title: name
  }, rest), initials);
}
Object.assign(__ds_scope, { Avatar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/actions/Avatar.jsx", error: String((e && e.message) || e) }); }

// components/actions/Badge.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const MAP = {
  accent: 'primary',
  'accent-2': 'critical',
  neutral: 'neutral',
  outline: 'outline'
};
function Badge({
  variant = 'primary',
  size = 'md',
  dot = true,
  children,
  className = '',
  ...rest
}) {
  const v = MAP[variant] || variant;
  return /*#__PURE__*/React.createElement("span", _extends({
    className: ['ds-chip', `ds-chip-${v}`, size === 'lg' ? 'ds-chip-lg' : '', dot ? '' : 'ds-chip-plain', className].filter(Boolean).join(' ')
  }, rest), children);
}
Object.assign(__ds_scope, { Badge });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/actions/Badge.jsx", error: String((e && e.message) || e) }); }

// components/actions/Icon.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
// Inline Lucide subset — the exact glyph set defined by
// resources/views/components/ui/icon.blade.php plus the sidebar glyphs from
// App\\Services\\Navigation\\NavigationRegistry. Stroke 2, 24x24, round caps.
const ICON_PATHS = {
  bell: '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
  user: '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
  users: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
  search: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
  'chevron-down': '<path d="m6 9 6 6 6-6"/>',
  'chevron-up': '<path d="m18 15-6-6-6 6"/>',
  'chevron-right': '<path d="m9 18 6-6-6-6"/>',
  'chevron-left': '<path d="m15 18-6-6 6-6"/>',
  check: '<path d="M20 6 9 17l-5-5"/>',
  play: '<polygon points="6 3 20 12 6 21 6 3"/>',
  lock: '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
  upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>',
  plus: '<path d="M5 12h14"/><path d="M12 5v14"/>',
  clock: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
  x: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
  'book-open': '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 1-3 3v14a3 3 0 0 1 3-3h7z"/>',
  book: '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
  award: '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>',
  settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
  'file-text': '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
  'message-square': '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
  'log-out': '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>',
  home: '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
  'arrow-left': '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
  'grip-vertical': '<circle cx="9" cy="12" r="1"/><circle cx="9" cy="5" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="19" r="1"/>',
  filter: '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
  eye: '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
  'eye-off': '<path d="M10.733 5.076a10.744 10.744 0 0 1 1.267-.076c7 0 10 7 10 7a13.16 13.16 0 0 1-1.670 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/>',
  edit: '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>',
  trash: '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
  menu: '<line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/>',
  'help-circle': '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 2-3 4"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
  dashboard: '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
  buildings: '<path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/>',
  clipboard: '<path d="M9 2h6a1 1 0 0 1 1 1v2H8V3a1 1 0 0 1 1-1z"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M9 12h6"/><path d="M9 16h6"/>',
  shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12h6"/>',
  info: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'
};
function Icon({
  name = 'info',
  size = 24,
  strokeWidth = 2,
  className = '',
  style,
  ...rest
}) {
  const paths = ICON_PATHS[name] || ICON_PATHS.info;
  return /*#__PURE__*/React.createElement("svg", _extends({
    className: `lucide lucide-${name} ${className}`.trim(),
    width: size,
    height: size,
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: strokeWidth,
    strokeLinecap: "round",
    strokeLinejoin: "round",
    style: {
      flexShrink: 0,
      ...style
    },
    "aria-hidden": "true",
    dangerouslySetInnerHTML: {
      __html: paths
    }
  }, rest));
}
Object.assign(__ds_scope, { ICON_PATHS, Icon });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/actions/Icon.jsx", error: String((e && e.message) || e) }); }

// components/actions/Button.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function Button({
  variant = 'primary',
  size = 'md',
  block = false,
  icon = null,
  trailingIcon = null,
  iconOnly = false,
  href,
  disabled = false,
  type = 'button',
  children,
  className = '',
  ...rest
}) {
  const cls = ['ds-btn', `ds-btn-${variant}`, size === 'sm' ? 'ds-btn-sm' : size === 'lg' ? 'ds-btn-lg' : '', block ? 'ds-btn-block' : '', iconOnly ? 'ds-btn-icon-only' : '', className].filter(Boolean).join(' ');
  const sz = size === 'sm' ? 16 : size === 'lg' ? 22 : 18;
  const inner = /*#__PURE__*/React.createElement(React.Fragment, null, icon && /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: icon,
    size: sz
  }), !iconOnly && children, trailingIcon && /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: trailingIcon,
    size: sz
  }));
  if (href) return /*#__PURE__*/React.createElement("a", _extends({
    href: href,
    className: cls,
    "aria-disabled": disabled || undefined,
    tabIndex: disabled ? -1 : undefined,
    "aria-label": iconOnly && typeof children === 'string' ? children : undefined
  }, rest), inner);
  return /*#__PURE__*/React.createElement("button", _extends({
    type: type,
    className: cls,
    disabled: disabled,
    "aria-label": iconOnly && typeof children === 'string' ? children : undefined
  }, rest), inner);
}
Object.assign(__ds_scope, { Button });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/actions/Button.jsx", error: String((e && e.message) || e) }); }

// components/actions/Chip.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function Chip({
  label,
  icon,
  selected = false,
  onToggle,
  className = '',
  ...rest
}) {
  return /*#__PURE__*/React.createElement("button", _extends({
    type: "button",
    "aria-pressed": selected,
    onClick: onToggle,
    className: `ds-chip ds-chip-plain ds-chip-outline ds-chip-filter ds-chip-lg ${className}`.trim(),
    style: selected ? {
      boxShadow: 'none'
    } : undefined
  }, rest), icon && /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: icon,
    size: 16
  }), label);
}
Object.assign(__ds_scope, { Chip });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/actions/Chip.jsx", error: String((e && e.message) || e) }); }

// components/actions/Fab.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function Fab({
  icon = 'plus',
  label,
  onClick,
  className = '',
  ...rest
}) {
  return /*#__PURE__*/React.createElement("button", _extends({
    type: "button",
    onClick: onClick,
    "aria-label": label || 'Ação',
    className: `ds-fab ${label ? '' : 'ds-fab-round'} ${className}`.trim()
  }, rest), /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: icon,
    size: 24
  }), label);
}
Object.assign(__ds_scope, { Fab });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/actions/Fab.jsx", error: String((e && e.message) || e) }); }

// components/app/NotificationsBell.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function NotificationsBell({
  unread = 0,
  items = [],
  onMarkAll,
  className = '',
  ...rest
}) {
  const [open, setOpen] = React.useState(false);
  return /*#__PURE__*/React.createElement("div", _extends({
    className: `ds-dropdown ${className}`.trim()
  }, rest), /*#__PURE__*/React.createElement("button", {
    type: "button",
    "aria-label": "Notifica\xE7\xF5es",
    "aria-expanded": open,
    className: "ds-btn ds-btn-ghost ds-btn-icon-only",
    style: {
      position: 'relative'
    },
    onClick: () => setOpen(o => !o)
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "bell",
    size: 22
  }), unread > 0 && /*#__PURE__*/React.createElement("span", {
    className: "ds-count-badge"
  }, unread > 99 ? '99+' : unread)), open && /*#__PURE__*/React.createElement("div", {
    className: "ds-dropdown-menu"
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      padding: 'var(--space-5)'
    }
  }, /*#__PURE__*/React.createElement("strong", {
    style: {
      fontSize: 'var(--font-size-h6)'
    }
  }, "Notifica\xE7\xF5es"), /*#__PURE__*/React.createElement("a", {
    href: "#",
    className: "ds-caption",
    style: {
      fontWeight: 700
    },
    onClick: e => {
      e.preventDefault();
      onMarkAll && onMarkAll();
    }
  }, "Marcar todas como lidas")), /*#__PURE__*/React.createElement("div", {
    style: {
      maxHeight: 420,
      overflowY: 'auto'
    }
  }, items.length === 0 ? /*#__PURE__*/React.createElement("div", {
    className: "ds-empty",
    style: {
      border: 'none',
      padding: 'var(--space-7)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    className: "ds-empty-icon"
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "bell",
    size: 28
  })), /*#__PURE__*/React.createElement("strong", null, "Tudo em ordem"), /*#__PURE__*/React.createElement("span", {
    className: "ds-caption"
  }, "Nenhuma notifica\xE7\xE3o por aqui.")) : items.map((n, i) => /*#__PURE__*/React.createElement("a", {
    key: n.id || i,
    href: "#",
    className: `ds-dropdown-item ${n.read ? '' : 'unread'}`.trim()
  }, /*#__PURE__*/React.createElement(__ds_scope.Avatar, {
    name: n.from || 'Plataforma EAD',
    size: "sm",
    tone: n.read ? 'neutral' : 'blue'
  }), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    className: "ds-body-sm"
  }, n.message), /*#__PURE__*/React.createElement("div", {
    className: "ds-caption",
    style: {
      marginTop: 4
    }
  }, n.time)))))));
}
Object.assign(__ds_scope, { NotificationsBell });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/app/NotificationsBell.jsx", error: String((e && e.message) || e) }); }

// components/data/Card.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function Card({
  kicker,
  title,
  meta,
  media,
  footer,
  variant = 'elevated',
  interactive = false,
  children,
  className = '',
  style,
  ...rest
}) {
  const cls = ['ds-card', variant === 'outlined' ? 'ds-card-outlined' : variant === 'elevated' ? 'ds-card-elevated' : '', interactive ? 'ds-card-interactive' : '', className].filter(Boolean).join(' ');
  return /*#__PURE__*/React.createElement("div", _extends({
    className: cls,
    style: style
  }, rest), media && /*#__PURE__*/React.createElement("div", {
    className: "ds-card-media"
  }, media), /*#__PURE__*/React.createElement("div", {
    className: "ds-card-body"
  }, kicker && /*#__PURE__*/React.createElement("div", {
    className: "ds-overline"
  }, kicker), title && /*#__PURE__*/React.createElement("h3", {
    className: "ds-card-title"
  }, title), children, meta && /*#__PURE__*/React.createElement("div", {
    className: "ds-card-meta"
  }, meta)), footer && /*#__PURE__*/React.createElement("div", {
    className: "ds-card-footer"
  }, footer));
}
Object.assign(__ds_scope, { Card });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/Card.jsx", error: String((e && e.message) || e) }); }

// components/data/EmptyState.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function EmptyState({
  icon = 'book-open',
  title,
  message,
  description,
  action,
  colspan,
  children,
  className = '',
  ...rest
}) {
  const headline = title || message;
  const sub = description || (title && message ? message : null);
  const body = /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
    className: "ds-empty-icon"
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: icon,
    size: 32
  })), children ? /*#__PURE__*/React.createElement("div", null, children) : headline ? /*#__PURE__*/React.createElement("h4", {
    style: {
      margin: 0
    }
  }, headline) : null, sub && /*#__PURE__*/React.createElement("p", {
    className: "ds-muted",
    style: {
      margin: 0,
      maxWidth: '46ch'
    }
  }, sub), action && /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: 'var(--space-3)'
    }
  }, action));
  if (colspan) return /*#__PURE__*/React.createElement("tr", _extends({
    className: className
  }, rest), /*#__PURE__*/React.createElement("td", {
    colSpan: colspan
  }, /*#__PURE__*/React.createElement("div", {
    className: "ds-empty",
    style: {
      border: 'none',
      background: 'transparent'
    }
  }, body)));
  return /*#__PURE__*/React.createElement("div", _extends({
    className: `ds-empty ${className}`.trim()
  }, rest), body);
}
Object.assign(__ds_scope, { EmptyState });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/EmptyState.jsx", error: String((e && e.message) || e) }); }

// components/data/Pagination.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function Pagination({
  current = 1,
  total = 1,
  onPage,
  label = 'Paginação',
  className = '',
  ...rest
}) {
  if (total <= 1) return null;
  const pages = [];
  for (let i = 1; i <= total; i++) {
    if (i === 1 || i === total || Math.abs(i - current) <= 1) pages.push(i);else if (pages[pages.length - 1] !== '…') pages.push('…');
  }
  return /*#__PURE__*/React.createElement("nav", _extends({
    "aria-label": label,
    className: className
  }, rest), /*#__PURE__*/React.createElement("ul", {
    className: "ds-pagination"
  }, /*#__PURE__*/React.createElement("li", {
    className: `ds-page-item ${current === 1 ? 'disabled' : ''}`
  }, /*#__PURE__*/React.createElement("a", {
    className: "ds-page-link",
    href: "#",
    "aria-label": "Anterior",
    onClick: e => {
      e.preventDefault();
      onPage && onPage(current - 1);
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "chevron-left",
    size: 18
  }))), pages.map((p, i) => p === '…' ? /*#__PURE__*/React.createElement("li", {
    key: 'g' + i,
    className: "ds-page-item disabled"
  }, /*#__PURE__*/React.createElement("span", {
    className: "ds-page-link"
  }, "\u2026")) : /*#__PURE__*/React.createElement("li", {
    key: p,
    className: `ds-page-item ${p === current ? 'active' : ''}`
  }, /*#__PURE__*/React.createElement("a", {
    className: "ds-page-link",
    href: "#",
    onClick: e => {
      e.preventDefault();
      onPage && onPage(p);
    }
  }, p))), /*#__PURE__*/React.createElement("li", {
    className: `ds-page-item ${current === total ? 'disabled' : ''}`
  }, /*#__PURE__*/React.createElement("a", {
    className: "ds-page-link",
    href: "#",
    "aria-label": "Pr\xF3ximo",
    onClick: e => {
      e.preventDefault();
      onPage && onPage(current + 1);
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "chevron-right",
    size: 18
  })))));
}
Object.assign(__ds_scope, { Pagination });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/Pagination.jsx", error: String((e && e.message) || e) }); }

// components/data/Progress.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function Progress({
  value = 0,
  max = 100,
  label = 'Progresso',
  variant = 'primary',
  height = 10,
  striped = false,
  showLabel = false,
  caption,
  className = '',
  style,
  ...rest
}) {
  const pct = Math.max(0, Math.min(100, Math.round(Number(value) / (Number(max) || 100) * 100)));
  const bg = variant === 'success' ? 'var(--secondary)' : variant === 'info' ? 'var(--tertiary)' : variant === 'neutral' ? 'var(--grey-400)' : 'var(--primary)';
  return /*#__PURE__*/React.createElement("div", {
    className: className,
    style: style
  }, (caption || showLabel) && /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      justifyContent: 'space-between',
      marginBottom: 8
    }
  }, /*#__PURE__*/React.createElement("span", {
    className: "ds-caption"
  }, caption || label), showLabel && /*#__PURE__*/React.createElement("span", {
    className: "ds-caption",
    style: {
      fontWeight: 700,
      color: 'var(--text-primary)'
    }
  }, pct, "%")), /*#__PURE__*/React.createElement("div", _extends({
    className: "ds-progress",
    role: "progressbar",
    "aria-label": label,
    "aria-valuenow": pct,
    "aria-valuemin": 0,
    "aria-valuemax": 100,
    style: {
      height
    }
  }, rest), /*#__PURE__*/React.createElement("div", {
    className: `ds-progress-bar ${striped ? 'ds-progress-bar-striped' : ''}`.trim(),
    style: {
      width: pct + '%',
      background: bg
    }
  })));
}
Object.assign(__ds_scope, { Progress });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/Progress.jsx", error: String((e && e.message) || e) }); }

// components/data/StatCard.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const TONES = {
  blue: {
    background: 'var(--primary-container)',
    color: 'var(--on-primary-container)'
  },
  mint: {
    background: 'var(--secondary-container)',
    color: 'var(--on-secondary-container)'
  },
  sky: {
    background: 'var(--tertiary-container)',
    color: 'var(--on-tertiary-container)'
  },
  neutral: {
    background: 'var(--surface-alt)',
    color: 'var(--grey-700)'
  }
};
function StatCard({
  kicker = 'Métrica',
  value = '0',
  delta,
  deltaVariant = 'success',
  icon,
  tone = 'blue',
  hint,
  className = '',
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    className: `ds-stat-card ${className}`.trim(),
    style: style
  }, rest), icon && /*#__PURE__*/React.createElement("div", {
    className: "ds-stat-icon",
    style: TONES[tone] || TONES.blue
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: icon,
    size: 24
  })), /*#__PURE__*/React.createElement("div", {
    className: "ds-overline"
  }, kicker), /*#__PURE__*/React.createElement("div", {
    className: "ds-stat-value"
  }, value), (delta || hint) && /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-3)',
      marginTop: 'var(--space-1)'
    }
  }, delta && /*#__PURE__*/React.createElement(__ds_scope.Badge, {
    variant: deltaVariant,
    dot: false
  }, delta), hint && /*#__PURE__*/React.createElement("span", {
    className: "ds-caption"
  }, hint)));
}
Object.assign(__ds_scope, { StatCard });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/StatCard.jsx", error: String((e && e.message) || e) }); }

// components/data/Table.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function Table({
  headers = [],
  header,
  footer,
  toolbar,
  hoverable = true,
  striped = false,
  children,
  className = '',
  ...rest
}) {
  const cls = ['ds-table', hoverable ? 'ds-table-hover' : '', striped ? 'ds-table-striped' : '', className].filter(Boolean).join(' ');
  return /*#__PURE__*/React.createElement("div", {
    className: "ds-table-wrap"
  }, toolbar && /*#__PURE__*/React.createElement("div", {
    className: "ds-table-toolbar"
  }, toolbar), /*#__PURE__*/React.createElement("div", {
    className: "ds-table-scroll"
  }, /*#__PURE__*/React.createElement("table", _extends({
    className: cls
  }, rest), (header || headers.length > 0) && /*#__PURE__*/React.createElement("thead", null, header || /*#__PURE__*/React.createElement("tr", null, headers.map(h => /*#__PURE__*/React.createElement("th", {
    key: h,
    scope: "col"
  }, h)))), /*#__PURE__*/React.createElement("tbody", null, children), footer && /*#__PURE__*/React.createElement("tfoot", null, footer))));
}
Object.assign(__ds_scope, { Table });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/Table.jsx", error: String((e && e.message) || e) }); }

// components/data/DataTable.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function DataTable({
  hover = true,
  ...rest
}) {
  return /*#__PURE__*/React.createElement(__ds_scope.Table, _extends({
    hoverable: hover
  }, rest));
}
Object.assign(__ds_scope, { DataTable });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/DataTable.jsx", error: String((e && e.message) || e) }); }

// components/data/Tabs.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function Tabs({
  tabs = [],
  value,
  onChange,
  className = '',
  style,
  ...rest
}) {
  const items = tabs.map(t => typeof t === 'string' ? {
    key: t,
    label: t
  } : t);
  return /*#__PURE__*/React.createElement("div", _extends({
    className: `ds-tabs ${className}`.trim(),
    role: "tablist",
    style: style
  }, rest), items.map(t => /*#__PURE__*/React.createElement("button", {
    key: t.key,
    type: "button",
    role: "tab",
    "aria-selected": t.key === value,
    className: "ds-tab",
    onClick: () => onChange && onChange(t.key)
  }, t.icon && /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: t.icon,
    size: 18
  }), t.label, t.count != null && /*#__PURE__*/React.createElement("span", {
    className: "ds-nav-badge",
    style: {
      marginLeft: 4
    }
  }, t.count))));
}
Object.assign(__ds_scope, { Tabs });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/Tabs.jsx", error: String((e && e.message) || e) }); }

// components/feedback/Alert.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const ICONS = {
  primary: 'info',
  success: 'check',
  info: 'info',
  attention: 'clock',
  danger: 'shield'
};
const MAP = {
  accent: 'primary',
  'accent-2': 'danger',
  warning: 'attention',
  secondary: 'attention'
};
function Alert({
  variant = 'primary',
  title,
  dismissable = false,
  onDismiss,
  action,
  children,
  className = '',
  style,
  ...rest
}) {
  const [open, setOpen] = React.useState(true);
  if (!open) return null;
  const v = MAP[variant] || variant;
  return /*#__PURE__*/React.createElement("div", _extends({
    role: "alert",
    className: `ds-alert ds-alert-${v} ${className}`.trim(),
    style: style
  }, rest), /*#__PURE__*/React.createElement("div", {
    className: "ds-alert-icon"
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: ICONS[v] || 'info',
    size: 20
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }, title && /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 700,
      marginBottom: 4
    }
  }, title), children, action && /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: 'var(--space-3)'
    }
  }, action)), dismissable && /*#__PURE__*/React.createElement("button", {
    type: "button",
    className: "ds-btn-close",
    "aria-label": "Fechar",
    onClick: () => {
      setOpen(false);
      onDismiss && onDismiss();
    }
  }, "\u2715"));
}
Object.assign(__ds_scope, { Alert });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/feedback/Alert.jsx", error: String((e && e.message) || e) }); }

// components/feedback/Modal.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function Modal({
  title,
  size = 'md',
  dismissable = true,
  onClose,
  actions,
  children,
  className = '',
  ...rest
}) {
  React.useEffect(() => {
    const onKey = e => {
      if (e.key === 'Escape' && dismissable && onClose) onClose();
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [dismissable, onClose]);
  return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
    className: "ds-modal-backdrop",
    onClick: dismissable ? onClose : undefined
  }), /*#__PURE__*/React.createElement("div", _extends({
    className: "ds-modal",
    role: "dialog",
    "aria-modal": "true",
    "aria-label": typeof title === 'string' ? title : undefined
  }, rest), /*#__PURE__*/React.createElement("div", {
    className: ['ds-modal-dialog', size === 'sm' ? 'ds-modal-sm' : size === 'lg' ? 'ds-modal-lg' : size === 'xl' ? 'ds-modal-xl' : '', className].filter(Boolean).join(' ')
  }, /*#__PURE__*/React.createElement("div", {
    className: "ds-modal-header"
  }, /*#__PURE__*/React.createElement("h2", {
    className: "ds-modal-title"
  }, title), dismissable && /*#__PURE__*/React.createElement("button", {
    type: "button",
    className: "ds-btn-close",
    "aria-label": "Fechar",
    onClick: onClose
  }, "\u2715")), /*#__PURE__*/React.createElement("div", {
    className: "ds-modal-body"
  }, children), actions && /*#__PURE__*/React.createElement("div", {
    className: "ds-modal-footer"
  }, actions))));
}
Object.assign(__ds_scope, { Modal });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/feedback/Modal.jsx", error: String((e && e.message) || e) }); }

// components/app/HelpButton.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function HelpButton({
  title,
  content,
  className = '',
  ...rest
}) {
  const [open, setOpen] = React.useState(false);
  return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("button", _extends({
    type: "button",
    "aria-label": "Ajuda desta tela",
    className: `ds-btn ds-btn-ghost ds-btn-icon-only ${className}`.trim(),
    onClick: () => setOpen(true)
  }, rest), /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "help-circle",
    size: 22
  })), open && /*#__PURE__*/React.createElement(__ds_scope.Modal, {
    title: title || 'Ajuda',
    size: "sm",
    onClose: () => setOpen(false),
    actions: /*#__PURE__*/React.createElement(__ds_scope.Button, {
      variant: "tonal",
      onClick: () => setOpen(false)
    }, "Entendi")
  }, /*#__PURE__*/React.createElement("p", {
    style: {
      margin: 0,
      lineHeight: 'var(--line-height-relaxed)',
      whiteSpace: 'pre-wrap'
    }
  }, content || 'Estamos preparando o conteúdo de ajuda desta tela.')));
}
Object.assign(__ds_scope, { HelpButton });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/app/HelpButton.jsx", error: String((e && e.message) || e) }); }

// components/feedback/ConfirmModal.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function ConfirmModal({
  title = 'Confirmar ação',
  message,
  confirmLabel = 'Confirmar',
  cancelLabel = 'Cancelar',
  variant = 'danger',
  onConfirm,
  onCancel,
  children,
  ...rest
}) {
  return /*#__PURE__*/React.createElement(__ds_scope.Modal, _extends({
    title: title,
    size: "sm",
    onClose: onCancel,
    actions: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(__ds_scope.Button, {
      variant: "ghost",
      onClick: onCancel
    }, cancelLabel), /*#__PURE__*/React.createElement(__ds_scope.Button, {
      variant: variant,
      onClick: onConfirm
    }, confirmLabel))
  }, rest), children || /*#__PURE__*/React.createElement("p", {
    style: {
      margin: 0
    }
  }, message || 'Esta ação não poderá ser desfeita. Deseja continuar?'));
}
Object.assign(__ds_scope, { ConfirmModal });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/feedback/ConfirmModal.jsx", error: String((e && e.message) || e) }); }

// components/actions/DeleteButton.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function DeleteButton({
  label = 'Remover',
  title = 'Confirmar exclusão',
  message,
  confirmLabel = 'Remover',
  size = 'sm',
  variant = 'ghost',
  onConfirm,
  className = '',
  ...rest
}) {
  const [open, setOpen] = React.useState(false);
  return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(__ds_scope.Button, _extends({
    variant: variant,
    size: size,
    icon: "trash",
    onClick: () => setOpen(true),
    className: className,
    style: {
      color: 'var(--critical)'
    }
  }, rest), label), open && /*#__PURE__*/React.createElement(__ds_scope.ConfirmModal, {
    title: title,
    message: message,
    confirmLabel: confirmLabel,
    onCancel: () => setOpen(false),
    onConfirm: () => {
      setOpen(false);
      onConfirm && onConfirm();
    }
  }));
}
Object.assign(__ds_scope, { DeleteButton });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/actions/DeleteButton.jsx", error: String((e && e.message) || e) }); }

// components/forms/Checkbox.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function Checkbox({
  name,
  label,
  checked,
  defaultChecked,
  value = '1',
  required = false,
  help,
  error,
  disabled = false,
  type = 'checkbox',
  id,
  onChange,
  className = '',
  ...rest
}) {
  const fieldId = id || name;
  return /*#__PURE__*/React.createElement("div", {
    className: `ds-check ${className}`.trim()
  }, /*#__PURE__*/React.createElement("input", _extends({
    className: "ds-check-input",
    type: type,
    id: fieldId,
    name: name,
    value: value,
    checked: checked,
    defaultChecked: defaultChecked,
    required: required,
    disabled: disabled,
    onChange: onChange
  }, rest)), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("label", {
    htmlFor: fieldId,
    className: "ds-check-label"
  }, label, required && /*#__PURE__*/React.createElement("span", {
    className: "ds-required"
  }, " *")), help && /*#__PURE__*/React.createElement("div", {
    className: "ds-form-text"
  }, help), error && /*#__PURE__*/React.createElement("div", {
    className: "ds-invalid-feedback"
  }, error)));
}
Object.assign(__ds_scope, { Checkbox });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Checkbox.jsx", error: String((e && e.message) || e) }); }

// components/forms/FieldStack.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function FieldStack({
  columns = 1,
  children,
  className = '',
  style,
  ...rest
}) {
  const n = Number(columns) || 1;
  return /*#__PURE__*/React.createElement("div", _extends({
    className: className,
    style: {
      display: 'grid',
      gridTemplateColumns: `repeat(${n}, minmax(0,1fr))`,
      columnGap: 'var(--space-5)',
      ...style
    }
  }, rest), children);
}
Object.assign(__ds_scope, { FieldStack });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/FieldStack.jsx", error: String((e && e.message) || e) }); }

// components/forms/FilterBar.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function FilterBar({
  submitLabel = 'Filtrar',
  resetLabel = 'Limpar',
  onReset,
  label = 'Filtros',
  children,
  actions,
  className = '',
  ...rest
}) {
  return /*#__PURE__*/React.createElement("form", _extends({
    role: "search",
    "aria-label": label,
    className: `ds-filter-bar ${className}`.trim()
  }, rest), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexWrap: 'wrap',
      gap: 'var(--space-4)',
      alignItems: 'center',
      flex: 1
    }
  }, children), actions || /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 'var(--space-3)'
    }
  }, onReset && /*#__PURE__*/React.createElement(__ds_scope.Button, {
    variant: "ghost",
    onClick: onReset
  }, resetLabel), /*#__PURE__*/React.createElement(__ds_scope.Button, {
    type: "submit",
    variant: "tonal",
    icon: "filter"
  }, submitLabel)));
}
Object.assign(__ds_scope, { FilterBar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/FilterBar.jsx", error: String((e && e.message) || e) }); }

// components/forms/FormActions.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function FormActions({
  align = 'end',
  children,
  className = '',
  style,
  ...rest
}) {
  const justify = align === 'end' ? 'flex-end' : align === 'between' ? 'space-between' : 'flex-start';
  return /*#__PURE__*/React.createElement("div", _extends({
    className: `ds-form-actions ${className}`.trim(),
    style: {
      justifyContent: justify,
      ...style
    }
  }, rest), children);
}
Object.assign(__ds_scope, { FormActions });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/FormActions.jsx", error: String((e && e.message) || e) }); }

// components/forms/Input.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function Input({
  label,
  name,
  type = 'text',
  value,
  defaultValue,
  placeholder,
  error,
  required = false,
  disabled = false,
  hint,
  id,
  rows,
  className = '',
  onChange,
  ...rest
}) {
  const fieldId = id || name;
  const [filled, setFilled] = React.useState(Boolean(defaultValue || value || placeholder));
  const Tag = type === 'textarea' ? 'textarea' : 'input';
  return /*#__PURE__*/React.createElement("div", {
    className: "ds-field"
  }, /*#__PURE__*/React.createElement("div", {
    className: `ds-field-shell ${filled ? 'filled' : ''}`.trim()
  }, /*#__PURE__*/React.createElement(Tag, _extends({
    id: fieldId,
    name: name
  }, type === 'textarea' ? {
    rows: rows || 4
  } : {
    type
  }, {
    value: value,
    defaultValue: defaultValue,
    placeholder: placeholder,
    required: required,
    disabled: disabled,
    "aria-invalid": error ? true : undefined,
    onChange: e => {
      setFilled(Boolean(e.target.value));
      onChange && onChange(e);
    },
    className: `ds-control ${error ? 'is-invalid' : ''} ${className}`.trim()
  }, rest)), label && /*#__PURE__*/React.createElement("label", {
    htmlFor: fieldId,
    className: "ds-float-label"
  }, label, required && /*#__PURE__*/React.createElement("span", {
    className: "ds-required"
  }, " *"))), hint && !error && /*#__PURE__*/React.createElement("div", {
    className: "ds-form-text"
  }, hint), error && /*#__PURE__*/React.createElement("div", {
    className: "ds-invalid-feedback"
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "info",
    size: 14
  }), error));
}
Object.assign(__ds_scope, { Input });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Input.jsx", error: String((e && e.message) || e) }); }

// components/forms/Select.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function Select({
  label,
  name,
  options = [],
  value,
  defaultValue,
  placeholder = 'Selecione',
  error,
  required = false,
  disabled = false,
  id,
  children,
  className = '',
  ...rest
}) {
  const fieldId = id || name;
  const list = Array.isArray(options) ? options.map(o => typeof o === 'string' ? {
    value: o,
    label: o
  } : o) : Object.entries(options).map(([v, l]) => ({
    value: v,
    label: l
  }));
  return /*#__PURE__*/React.createElement("div", {
    className: "ds-field"
  }, label && /*#__PURE__*/React.createElement("label", {
    htmlFor: fieldId,
    className: "ds-label"
  }, label, required && /*#__PURE__*/React.createElement("span", {
    className: "ds-required"
  }, " *")), /*#__PURE__*/React.createElement("select", _extends({
    id: fieldId,
    name: name,
    value: value,
    defaultValue: defaultValue,
    required: required,
    disabled: disabled,
    "aria-invalid": error ? true : undefined,
    className: `ds-select ${error ? 'is-invalid' : ''} ${className}`.trim(),
    style: {
      paddingTop: 14,
      paddingBottom: 14
    }
  }, rest), placeholder && /*#__PURE__*/React.createElement("option", {
    value: ""
  }, placeholder), list.map(o => /*#__PURE__*/React.createElement("option", {
    key: o.value,
    value: o.value
  }, o.label)), children), error && /*#__PURE__*/React.createElement("div", {
    className: "ds-invalid-feedback"
  }, error));
}
Object.assign(__ds_scope, { Select });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Select.jsx", error: String((e && e.message) || e) }); }

// components/forms/Switch.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function Switch({
  label,
  help,
  checked,
  defaultChecked,
  disabled = false,
  onChange,
  name,
  id,
  className = '',
  ...rest
}) {
  const fieldId = id || name;
  return /*#__PURE__*/React.createElement("div", {
    className: className
  }, /*#__PURE__*/React.createElement("label", {
    className: "ds-switch",
    htmlFor: fieldId
  }, /*#__PURE__*/React.createElement("input", _extends({
    type: "checkbox",
    id: fieldId,
    name: name,
    checked: checked,
    defaultChecked: defaultChecked,
    disabled: disabled,
    onChange: onChange
  }, rest)), /*#__PURE__*/React.createElement("span", {
    className: "ds-switch-track"
  }, /*#__PURE__*/React.createElement("span", {
    className: "ds-switch-thumb"
  })), /*#__PURE__*/React.createElement("span", null, label)), help && /*#__PURE__*/React.createElement("div", {
    className: "ds-form-text",
    style: {
      marginLeft: 70
    }
  }, help));
}
Object.assign(__ds_scope, { Switch });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Switch.jsx", error: String((e && e.message) || e) }); }

// components/forms/Textarea.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function Textarea({
  help,
  hint,
  rows = 4,
  ...rest
}) {
  return /*#__PURE__*/React.createElement(__ds_scope.Input, _extends({
    type: "textarea",
    rows: rows,
    hint: hint || help
  }, rest));
}
Object.assign(__ds_scope, { Textarea });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Textarea.jsx", error: String((e && e.message) || e) }); }

// components/layout/Footer.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function Footer({
  brand = 'Plataforma EAD',
  links = ['Termos de uso', 'Privacidade', 'Suporte'],
  className = '',
  ...rest
}) {
  return /*#__PURE__*/React.createElement("footer", _extends({
    className: `ds-footer ${className}`.trim()
  }, rest), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexWrap: 'wrap',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: 'var(--space-5)',
      maxWidth: 'var(--content-max)',
      margin: '0 auto'
    }
  }, /*#__PURE__*/React.createElement("div", null, "\xA9 ", new Date().getFullYear(), " ", /*#__PURE__*/React.createElement("strong", null, brand), ". Todos os direitos reservados."), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 'var(--space-7)'
    }
  }, links.map(l => /*#__PURE__*/React.createElement("a", {
    key: l,
    href: "#"
  }, l)))));
}
Object.assign(__ds_scope, { Footer });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/Footer.jsx", error: String((e && e.message) || e) }); }

// components/layout/GuestPanel.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function GuestPanel({
  brand = 'Plataforma EAD',
  mark = 'EAD',
  title = 'Aprender no seu ritmo, com certificado no fim.',
  text = 'Cursos, provas e certificados oficiais da sua organização, num só lugar.',
  highlights = ['Aulas em vídeo e materiais em PDF', 'Provas com correção automática', 'Certificados com validação pública'],
  className = '',
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    className: `ds-guest-panel ${className}`.trim()
  }, rest), /*#__PURE__*/React.createElement("div", {
    className: "ds-brand"
  }, /*#__PURE__*/React.createElement("span", {
    className: "ds-brand-mark"
  }, mark), brand), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 'var(--space-6)'
    }
  }, /*#__PURE__*/React.createElement("h1", {
    className: "ds-guest-title"
  }, title), /*#__PURE__*/React.createElement("p", {
    className: "ds-guest-text",
    style: {
      margin: 0
    }
  }, text), /*#__PURE__*/React.createElement("div", {
    className: "ds-guest-illustration"
  }, highlights.map(h => /*#__PURE__*/React.createElement("div", {
    key: h,
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-3)'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: 32,
      height: 32,
      borderRadius: '50%',
      background: 'var(--secondary-container)',
      color: 'var(--on-secondary-container)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      flex: 'none'
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "check",
    size: 18
  })), /*#__PURE__*/React.createElement("span", {
    className: "ds-body-sm"
  }, h))))), /*#__PURE__*/React.createElement("div", {
    className: "ds-caption"
  }, "\xA9 ", new Date().getFullYear(), " ", brand, ". Todos os direitos reservados."));
}
Object.assign(__ds_scope, { GuestPanel });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/GuestPanel.jsx", error: String((e && e.message) || e) }); }

// components/layout/PageHeader.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function PageHeader({
  title,
  kicker,
  subtitle,
  actions,
  breadcrumb,
  className = '',
  ...rest
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    className: `ds-page-header ${className}`.trim()
  }, rest), /*#__PURE__*/React.createElement("div", null, breadcrumb && /*#__PURE__*/React.createElement("div", {
    className: "ds-caption",
    style: {
      marginBottom: 'var(--space-3)'
    }
  }, breadcrumb), kicker && /*#__PURE__*/React.createElement("div", {
    className: "ds-overline",
    style: {
      color: 'var(--primary)',
      marginBottom: 'var(--space-2)'
    }
  }, kicker), /*#__PURE__*/React.createElement("h1", {
    className: "ds-page-title"
  }, title), subtitle && /*#__PURE__*/React.createElement("p", {
    className: "ds-lead",
    style: {
      margin: 'var(--space-3) 0 0',
      maxWidth: '62ch'
    }
  }, subtitle)), actions && /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexWrap: 'wrap',
      gap: 'var(--space-3)'
    }
  }, actions));
}
Object.assign(__ds_scope, { PageHeader });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/PageHeader.jsx", error: String((e && e.message) || e) }); }

// components/layout/Sidebar.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const DEFAULT_SECTIONS = [{
  title: 'Painel',
  items: [{
    key: 'dashboard',
    label: 'Dashboard',
    icon: 'dashboard'
  }, {
    key: 'courses',
    label: 'Cursos e módulos',
    icon: 'book'
  }, {
    key: 'users',
    label: 'Alunos e usuários',
    icon: 'users'
  }]
}, {
  title: 'Acompanhamento',
  items: [{
    key: 'quiz-attempts',
    label: 'Redações pendentes',
    icon: 'clipboard',
    badge: 4
  }, {
    key: 'forum-moderation',
    label: 'Moderação do fórum',
    icon: 'shield',
    badge: 2
  }, {
    key: 'audit-logs',
    label: 'Auditoria',
    icon: 'file-text'
  }]
}, {
  title: 'Aprendizado',
  items: [{
    key: 'my-courses',
    label: 'Meus cursos',
    icon: 'home'
  }, {
    key: 'forum',
    label: 'Fórum de dúvidas',
    icon: 'message-square'
  }, {
    key: 'settings',
    label: 'Configurações',
    icon: 'settings'
  }]
}];
function Sidebar({
  sections = DEFAULT_SECTIONS,
  activeKey,
  onNavigate,
  footer,
  className = '',
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("nav", _extends({
    className: `ds-nav ${className}`.trim(),
    style: style,
    "aria-label": "Navega\xE7\xE3o principal"
  }, rest), sections.map((section, si) => /*#__PURE__*/React.createElement(React.Fragment, {
    key: section.title
  }, /*#__PURE__*/React.createElement("div", {
    className: "ds-nav-section",
    style: si === 0 ? {
      paddingTop: 0
    } : undefined
  }, section.title), section.items.map(item => /*#__PURE__*/React.createElement("a", {
    key: item.key,
    href: item.url || '#',
    className: `ds-nav-item ${item.key === activeKey ? 'active' : ''}`.trim(),
    "aria-current": item.key === activeKey ? 'page' : undefined,
    onClick: e => {
      if (onNavigate) {
        e.preventDefault();
        onNavigate(item.key);
      }
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: item.icon,
    size: 20
  }), /*#__PURE__*/React.createElement("span", null, item.label), item.badge != null && /*#__PURE__*/React.createElement("span", {
    className: "ds-nav-badge"
  }, item.badge))))), footer && /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: 'auto',
      padding: 'var(--space-4)'
    }
  }, footer));
}
Object.assign(__ds_scope, { DEFAULT_SECTIONS, Sidebar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/Sidebar.jsx", error: String((e && e.message) || e) }); }

// components/layout/Topbar.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function Topbar({
  brand = 'Plataforma EAD',
  mark = 'EAD',
  user,
  activeOrganization,
  onExitImpersonation,
  search = true,
  searchPlaceholder = 'Buscar cursos, alunos, certificados…',
  right,
  className = '',
  ...rest
}) {
  return /*#__PURE__*/React.createElement("header", _extends({
    className: `ds-appbar ${className}`.trim()
  }, rest), /*#__PURE__*/React.createElement("a", {
    href: "#",
    className: "ds-brand"
  }, /*#__PURE__*/React.createElement("span", {
    className: "ds-brand-mark"
  }, mark), brand), search && /*#__PURE__*/React.createElement("label", {
    className: "ds-search"
  }, /*#__PURE__*/React.createElement(__ds_scope.Icon, {
    name: "search",
    size: 20
  }), /*#__PURE__*/React.createElement("input", {
    type: "search",
    placeholder: searchPlaceholder,
    "aria-label": "Buscar"
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-4)'
    }
  }, activeOrganization && /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-3)'
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Badge, {
    variant: "info"
  }, activeOrganization), onExitImpersonation && /*#__PURE__*/React.createElement(__ds_scope.Button, {
    variant: "ghost",
    size: "sm",
    onClick: onExitImpersonation
  }, "Sair do contexto")), right, user ? /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-3)',
      paddingLeft: 'var(--space-4)',
      borderLeft: '1px solid var(--divider)'
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Avatar, {
    name: user.name
  }), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 700,
      lineHeight: 1.3
    }
  }, user.name), /*#__PURE__*/React.createElement("div", {
    className: "ds-caption"
  }, user.role)), /*#__PURE__*/React.createElement(__ds_scope.Button, {
    iconOnly: true,
    icon: "chevron-down",
    variant: "ghost",
    size: "sm"
  }, "Conta")) : /*#__PURE__*/React.createElement(__ds_scope.Button, null, "Entrar")));
}
Object.assign(__ds_scope, { Topbar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/Topbar.jsx", error: String((e && e.message) || e) }); }

// ui_kits/lms_app/data.js
try { (() => {
window.EAD_DATA = {
  org: 'Conselho Regional',
  mark: 'CR',
  user: {
    name: 'Joana Prado',
    role: 'Gestora'
  },
  stats: {
    active_students: 248,
    certificates_issued: 112,
    completion_rate: 63,
    courses_count: 9
  },
  enrollments: [{
    student: 'Ana Beatriz Lima',
    email: 'ana.lima@conselho.br',
    course: 'Fundamentos de Segurança',
    progress: 62,
    status: 'Em andamento',
    variant: 'info',
    tone: 'blue'
  }, {
    student: 'Carlos Eduardo Souza',
    email: 'carlos.souza@conselho.br',
    course: 'Boas Práticas de Atendimento',
    progress: 18,
    status: 'Em andamento',
    variant: 'info',
    tone: 'sky'
  }, {
    student: 'Marina Duarte',
    email: 'marina.duarte@conselho.br',
    course: 'Fundamentos de Segurança',
    progress: 100,
    status: 'Concluída',
    variant: 'success',
    tone: 'mint'
  }, {
    student: 'Rafael Nogueira',
    email: 'rafael.nogueira@conselho.br',
    course: 'Ética Profissional',
    progress: 45,
    status: 'Em andamento',
    variant: 'info',
    tone: 'neutral'
  }],
  attention: [{
    label: 'Redações aguardando correção',
    count: 4,
    icon: 'clipboard',
    go: 'essays'
  }, {
    label: 'Denúncias no fórum',
    count: 2,
    icon: 'shield',
    go: 'forum'
  }, {
    label: 'Certificados prontos para emissão',
    count: 7,
    icon: 'award',
    go: 'courses'
  }],
  topCourses: [{
    title: 'Boas Práticas de Atendimento',
    rate: 88
  }, {
    title: 'Fundamentos de Segurança',
    rate: 63
  }, {
    title: 'Ética Profissional',
    rate: 41
  }],
  courses: [{
    id: 1,
    title: 'Fundamentos de Segurança do Trabalho',
    hours: 4,
    modules: 2,
    lessons: 13,
    students: 96,
    published: true
  }, {
    id: 2,
    title: 'Boas Práticas de Atendimento',
    hours: 6,
    modules: 3,
    lessons: 18,
    students: 74,
    published: true
  }, {
    id: 3,
    title: 'Ética Profissional',
    hours: 3,
    modules: 2,
    lessons: 9,
    students: 31,
    published: false
  }],
  myCourses: [{
    id: 1,
    org: 'Conselho Regional',
    title: 'Fundamentos de Segurança do Trabalho',
    description: 'Conteúdo introdutório obrigatório para toda a equipe técnica.',
    progress: 62,
    lessons: 13,
    hours: 4,
    deadline: 'Prazo: 30/08'
  }, {
    id: 2,
    org: 'Conselho Regional',
    title: 'Boas Práticas de Atendimento',
    description: 'Comunicação, escuta ativa e registro de ocorrências.',
    progress: 100,
    lessons: 18,
    hours: 6,
    deadline: 'Concluído em 12/07'
  }, {
    id: 3,
    org: 'Instituto Técnico Sul',
    title: 'Ética Profissional',
    description: 'Deveres, sigilo e conduta em situações de conflito.',
    progress: 0,
    lessons: 9,
    hours: 3,
    deadline: 'Prazo: 15/09'
  }],
  modules: [{
    id: 1,
    title: 'Conceitos gerais',
    lessons: [{
      id: 11,
      title: 'Apresentação do curso',
      type: 'video',
      meta: 'Vídeo · 6 min',
      done: true
    }, {
      id: 12,
      title: 'Normas regulamentadoras',
      type: 'video',
      meta: 'Vídeo · 14 min',
      done: true
    }, {
      id: 13,
      title: 'Leitura complementar',
      type: 'pdf',
      meta: 'PDF · 8 páginas',
      done: false
    }]
  }, {
    id: 2,
    title: 'Prática e avaliação',
    lessons: [{
      id: 21,
      title: 'Equipamentos de proteção',
      type: 'video',
      meta: 'Vídeo · 21 min',
      done: false
    }, {
      id: 22,
      title: 'Prova do módulo 2',
      type: 'quiz',
      meta: '3 questões · 20 min',
      done: false
    }]
  }],
  materials: [{
    name: 'NR-35 — trabalho em altura.pdf',
    size: 'PDF · 1,2 MB'
  }, {
    name: 'Checklist de inspeção.pdf',
    size: 'PDF · 320 KB'
  }],
  notifications: [{
    message: 'Matrícula confirmada em Fundamentos de Segurança do Trabalho.',
    time: 'há 2 horas',
    from: 'Conselho Regional'
  }, {
    message: 'Nova resposta no tópico "Dúvida sobre a prova final".',
    time: 'há 5 horas',
    read: true,
    from: 'Ana Beatriz Lima'
  }, {
    message: 'Certificado emitido para Boas Práticas de Atendimento.',
    time: 'ontem',
    read: true,
    from: 'Plataforma EAD'
  }],
  topics: [{
    id: 1,
    title: 'Dúvida sobre a prova final',
    author: 'Ana Beatriz Lima',
    excerpt: 'A prova do módulo 2 vale nota para o certificado ou é apenas diagnóstica?',
    replies: 4,
    pinned: true,
    time: 'há 3 horas'
  }, {
    id: 2,
    title: 'Material complementar do módulo 2',
    author: 'Marina Duarte',
    excerpt: 'Compartilhando o checklist que usamos na inspeção da semana passada.',
    replies: 1,
    pinned: false,
    time: 'há 2 dias'
  }, {
    id: 3,
    title: 'Prazo para conclusão',
    author: 'Carlos Eduardo Souza',
    excerpt: 'O prazo de 30/08 vale para quem entrou na turma agora?',
    replies: 6,
    pinned: false,
    time: 'há 4 dias'
  }],
  essays: [{
    id: 1,
    student: 'Ana Beatriz Lima',
    quiz: 'Prova do módulo 2 — Segurança',
    sent: '16/08/2026 14:02'
  }, {
    id: 2,
    student: 'Rafael Nogueira',
    quiz: 'Prova final — Ética Profissional',
    sent: '15/08/2026 09:31'
  }],
  quiz: {
    title: 'Segurança em trabalho em altura',
    instructions: 'São 3 questões e 20 minutos. Você pode salvar e voltar depois; o tempo continua contando.',
    questions: [{
      id: 1,
      type: 'Única escolha',
      text: 'Qual equipamento é obrigatório em trabalho em altura?',
      options: ['Cinto de segurança tipo paraquedista', 'Óculos de proteção', 'Protetor auricular']
    }, {
      id: 2,
      type: 'Múltipla escolha',
      text: 'Quais itens compõem a análise preliminar de risco?',
      options: ['Identificação da tarefa', 'Classificação do risco', 'Cor do uniforme']
    }, {
      id: 3,
      type: 'Dissertativa',
      text: 'Descreva o procedimento de bloqueio e etiquetagem antes de uma manutenção.'
    }]
  }
};
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/lms_app/data.js", error: String((e && e.message) || e) }); }

// ui_kits/lms_app/screens.jsx
try { (() => {
const NS = window.PlataformaEADDesignSystem_618bb2;
const {
  Button,
  Fab,
  Badge,
  Chip,
  Avatar,
  Icon,
  DeleteButton,
  Card,
  StatCard,
  Table,
  DataTable,
  Progress,
  EmptyState,
  Pagination,
  Tabs,
  Alert,
  Modal,
  Input,
  Textarea,
  Select,
  Checkbox,
  Switch,
  FieldStack,
  FormActions,
  FilterBar,
  PageHeader
} = NS;
const D = window.EAD_DATA;
const Section = ({
  title,
  action,
  children
}) => /*#__PURE__*/React.createElement("section", {
  className: "ds-section"
}, /*#__PURE__*/React.createElement("div", {
  style: {
    display: 'flex',
    flexWrap: 'wrap',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 'var(--space-4)',
    marginBottom: 'var(--space-5)'
  }
}, /*#__PURE__*/React.createElement("h2", {
  style: {
    fontSize: 'var(--font-size-h3)'
  }
}, title), action), children);
function DashboardScreen({
  go
}) {
  const [range, setRange] = React.useState('30d');
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(PageHeader, {
    kicker: "Painel",
    title: "Bom dia, Joana",
    subtitle: "Um panorama da sua organiza\xE7\xE3o nos \xFAltimos 30 dias.",
    actions: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(Button, {
      variant: "tonal",
      icon: "file-text"
    }, "Exportar CSV"), /*#__PURE__*/React.createElement(Button, {
      icon: "plus",
      onClick: () => go('courses')
    }, "Novo curso"))
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 'var(--space-4)',
      marginBottom: 'var(--space-6)',
      flexWrap: 'wrap'
    }
  }, [['7d', 'Últimos 7 dias'], ['30d', 'Últimos 30 dias'], ['ano', 'Este ano']].map(([k, l]) => /*#__PURE__*/React.createElement(Chip, {
    key: k,
    label: l,
    selected: range === k,
    onToggle: () => setRange(k)
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(auto-fit,minmax(240px,1fr))',
      gap: 'var(--space-5)',
      marginBottom: 'var(--gap-section)'
    }
  }, /*#__PURE__*/React.createElement(StatCard, {
    icon: "users",
    tone: "blue",
    kicker: "Alunos ativos",
    value: D.stats.active_students,
    delta: "+4,2%",
    hint: "vs. per\xEDodo anterior"
  }), /*#__PURE__*/React.createElement(StatCard, {
    icon: "award",
    tone: "mint",
    kicker: "Certificados emitidos",
    value: D.stats.certificates_issued,
    delta: "+12%",
    hint: "no per\xEDodo"
  }), /*#__PURE__*/React.createElement(StatCard, {
    icon: "check",
    tone: "sky",
    kicker: "Taxa de conclus\xE3o",
    value: D.stats.completion_rate + '%',
    hint: "m\xE9dia dos cursos"
  }), /*#__PURE__*/React.createElement(StatCard, {
    icon: "book",
    tone: "neutral",
    kicker: "Cursos publicados",
    value: D.stats.courses_count,
    hint: "2 em rascunho"
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'minmax(0,2fr) minmax(0,1fr)',
      gap: 'var(--space-6)',
      alignItems: 'start'
    }
  }, /*#__PURE__*/React.createElement(Table, {
    hoverable: true,
    toolbar: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("h2", {
      style: {
        fontSize: 'var(--font-size-h4)'
      }
    }, "Matr\xEDculas recentes"), /*#__PURE__*/React.createElement("p", {
      className: "ds-caption",
      style: {
        margin: '4px 0 0'
      }
    }, "Atualizado h\xE1 5 minutos")), /*#__PURE__*/React.createElement(Button, {
      variant: "ghost",
      trailingIcon: "chevron-right"
    }, "Ver todas")),
    headers: ['Aluno', 'Curso', 'Progresso', 'Status']
  }, D.enrollments.map(e => /*#__PURE__*/React.createElement("tr", {
    key: e.student
  }, /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-3)'
    }
  }, /*#__PURE__*/React.createElement(Avatar, {
    name: e.student,
    size: "sm",
    tone: e.tone
  }), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 600
    }
  }, e.student), /*#__PURE__*/React.createElement("div", {
    className: "ds-caption"
  }, e.email)))), /*#__PURE__*/React.createElement("td", {
    className: "ds-muted"
  }, e.course), /*#__PURE__*/React.createElement("td", {
    style: {
      minWidth: 160
    }
  }, /*#__PURE__*/React.createElement(Progress, {
    value: e.progress,
    height: 8
  })), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement(Badge, {
    variant: e.variant
  }, e.status))))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 'var(--space-5)'
    }
  }, /*#__PURE__*/React.createElement(Card, {
    title: "Precisa da sua aten\xE7\xE3o"
  }, D.attention.map(a => /*#__PURE__*/React.createElement("a", {
    key: a.label,
    href: "#",
    onClick: e => {
      e.preventDefault();
      go(a.go);
    },
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-3)',
      padding: 'var(--space-3) 0',
      borderTop: '1px solid var(--divider)',
      color: 'var(--text-primary)'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: 40,
      height: 40,
      borderRadius: 12,
      background: 'var(--tertiary-container)',
      color: 'var(--on-tertiary-container)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      flex: 'none'
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: a.icon,
    size: 20
  })), /*#__PURE__*/React.createElement("span", {
    style: {
      flex: 1
    }
  }, a.label), /*#__PURE__*/React.createElement(Badge, {
    variant: "info",
    dot: false
  }, a.count)))), /*#__PURE__*/React.createElement(Card, {
    variant: "outlined",
    title: "Cursos mais conclu\xEDdos"
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 'var(--space-4)'
    }
  }, D.topCourses.map(c => /*#__PURE__*/React.createElement(Progress, {
    key: c.title,
    value: c.rate,
    showLabel: true,
    caption: c.title,
    variant: "success"
  })))))));
}
function CoursesAdminScreen({
  go
}) {
  const [page, setPage] = React.useState(1);
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(PageHeader, {
    kicker: "Gest\xE3o",
    title: "Cursos e m\xF3dulos",
    subtitle: "Organize o conte\xFAdo, as provas e as regras de conclus\xE3o da sua organiza\xE7\xE3o.",
    actions: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(Button, {
      variant: "tonal",
      icon: "upload"
    }, "Importar alunos"), /*#__PURE__*/React.createElement(Button, {
      icon: "plus"
    }, "Novo curso"))
  }), /*#__PURE__*/React.createElement(FilterBar, {
    onReset: () => {}
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      minWidth: 240
    }
  }, /*#__PURE__*/React.createElement(Input, {
    name: "q",
    type: "search",
    label: "Buscar curso"
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      width: 200
    }
  }, /*#__PURE__*/React.createElement(Select, {
    name: "status",
    label: "Status",
    options: ['Publicado', 'Rascunho']
  }))), /*#__PURE__*/React.createElement(DataTable, {
    headers: ['Curso', 'Carga', 'Alunos', 'Status', 'Ações']
  }, D.courses.map(c => /*#__PURE__*/React.createElement("tr", {
    key: c.id
  }, /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 600
    }
  }, c.title), /*#__PURE__*/React.createElement("div", {
    className: "ds-caption"
  }, c.modules, " m\xF3dulos \xB7 ", c.lessons, " aulas")), /*#__PURE__*/React.createElement("td", null, c.hours, "h"), /*#__PURE__*/React.createElement("td", null, c.students), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement(Badge, {
    variant: c.published ? 'success' : 'neutral',
    dot: c.published
  }, c.published ? 'Publicado' : 'Rascunho')), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 'var(--space-2)',
      flexWrap: 'wrap'
    }
  }, /*#__PURE__*/React.createElement(Button, {
    variant: "tonal",
    size: "sm",
    onClick: () => go('classroom')
  }, "Abrir"), /*#__PURE__*/React.createElement(Button, {
    variant: "ghost",
    size: "sm",
    icon: "edit"
  }, "Editar"), /*#__PURE__*/React.createElement(DeleteButton, {
    message: 'O curso "' + c.title + '" e suas matrículas serão removidos.'
  })))))), /*#__PURE__*/React.createElement(Pagination, {
    current: page,
    total: 4,
    onPage: setPage
  }));
}
function MyCoursesScreen({
  go
}) {
  const [tab, setTab] = React.useState('ativos');
  const list = D.myCourses.filter(c => tab === 'todos' || (tab === 'ativos' ? c.progress < 100 : c.progress === 100));
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(PageHeader, {
    kicker: "Aprendizado",
    title: "Meus cursos",
    subtitle: "Suas matr\xEDculas em todas as organiza\xE7\xF5es, com progresso em tempo real."
  }), /*#__PURE__*/React.createElement(Tabs, {
    value: tab,
    onChange: setTab,
    style: {
      maxWidth: 520,
      marginBottom: 'var(--space-7)'
    },
    tabs: [{
      key: 'ativos',
      label: 'Em andamento',
      count: D.myCourses.filter(c => c.progress < 100).length
    }, {
      key: 'feitos',
      label: 'Concluídos'
    }, {
      key: 'todos',
      label: 'Todos'
    }]
  }), list.length === 0 ? /*#__PURE__*/React.createElement(EmptyState, {
    icon: "book-open",
    title: "Nada por aqui ainda",
    description: "Quando sua organiza\xE7\xE3o matricular voc\xEA em um curso, ele aparece nesta lista."
  }) : /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(auto-fill,minmax(320px,1fr))',
      gap: 'var(--space-6)'
    }
  }, list.map(c => /*#__PURE__*/React.createElement(Card, {
    key: c.id,
    interactive: true,
    onClick: () => go('classroom'),
    media: /*#__PURE__*/React.createElement("div", {
      style: {
        height: '100%',
        display: 'flex',
        alignItems: 'flex-end',
        padding: 'var(--space-5)'
      }
    }, /*#__PURE__*/React.createElement(Badge, {
      variant: c.progress === 100 ? 'success' : 'info'
    }, c.progress === 100 ? 'Concluído' : 'Em andamento')),
    kicker: c.org,
    title: c.title,
    meta: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("span", null, c.lessons, " aulas"), /*#__PURE__*/React.createElement("span", null, c.hours, "h"), /*#__PURE__*/React.createElement("span", null, c.deadline))
  }, /*#__PURE__*/React.createElement("p", {
    className: "ds-muted",
    style: {
      margin: 0
    }
  }, c.description), /*#__PURE__*/React.createElement(Progress, {
    value: c.progress,
    showLabel: true,
    caption: "Progresso",
    variant: c.progress === 100 ? 'success' : 'primary'
  }), /*#__PURE__*/React.createElement(Button, {
    variant: c.progress === 100 ? 'tonal' : 'primary',
    trailingIcon: "chevron-right"
  }, c.progress === 100 ? 'Baixar certificado' : 'Continuar')))));
}
function ClassroomScreen({
  go
}) {
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(PageHeader, {
    breadcrumb: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => {
        e.preventDefault();
        go('my-courses');
      }
    }, "Meus cursos"), " / Fundamentos de Seguran\xE7a do Trabalho"),
    kicker: "Sala de aula",
    title: "Fundamentos de Seguran\xE7a do Trabalho",
    actions: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(Button, {
      variant: "secondary",
      icon: "message-square",
      onClick: () => go('forum')
    }, "F\xF3rum"), /*#__PURE__*/React.createElement(Button, {
      icon: "play",
      onClick: () => go('lesson')
    }, "Continuar de onde parei"))
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'minmax(0,2fr) minmax(0,1fr)',
      gap: 'var(--space-6)',
      alignItems: 'start'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 'var(--space-5)'
    }
  }, D.modules.map(m => /*#__PURE__*/React.createElement(Card, {
    key: m.id,
    title: m.title,
    kicker: `Módulo ${m.id}`
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column'
    }
  }, m.lessons.map(l => /*#__PURE__*/React.createElement("a", {
    key: l.id,
    href: "#",
    onClick: e => {
      e.preventDefault();
      go(l.type === 'quiz' ? 'quiz' : 'lesson');
    },
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-4)',
      padding: 'var(--space-4) 0',
      borderTop: '1px solid var(--divider)',
      color: 'var(--text-primary)'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: 44,
      height: 44,
      borderRadius: '50%',
      flex: 'none',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      background: l.done ? 'var(--secondary-container)' : 'var(--primary-container)',
      color: l.done ? 'var(--on-secondary-container)' : 'var(--on-primary-container)'
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: l.done ? 'check' : l.type === 'quiz' ? 'clipboard' : l.type === 'pdf' ? 'file-text' : 'play',
    size: 20
  })), /*#__PURE__*/React.createElement("span", {
    style: {
      flex: 1
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'block',
      fontWeight: 600
    }
  }, l.title), /*#__PURE__*/React.createElement("span", {
    className: "ds-caption"
  }, l.meta)), l.type === 'quiz' && /*#__PURE__*/React.createElement(Badge, {
    variant: "outline",
    dot: false
  }, "Prova"), /*#__PURE__*/React.createElement(Icon, {
    name: "chevron-right",
    size: 20
  }))))))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 'var(--space-5)'
    }
  }, /*#__PURE__*/React.createElement(Card, {
    title: "Seu progresso"
  }, /*#__PURE__*/React.createElement(Progress, {
    value: 62,
    showLabel: true,
    caption: "8 de 13 aulas conclu\xEDdas"
  }), /*#__PURE__*/React.createElement("div", {
    className: "ds-card-meta",
    style: {
      marginTop: 'var(--space-4)'
    }
  }, /*#__PURE__*/React.createElement("span", null, "In\xEDcio: 02/06/2026"), /*#__PURE__*/React.createElement("span", null, "Prazo: 30/08/2026"))), /*#__PURE__*/React.createElement(Alert, {
    variant: "info",
    title: "Certificado em breve"
  }, "Conclua 100% das aulas e a prova final para liberar a emiss\xE3o."), /*#__PURE__*/React.createElement(Card, {
    variant: "outlined",
    title: "Instrutor"
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-3)'
    }
  }, /*#__PURE__*/React.createElement(Avatar, {
    name: "Paulo Ferraz",
    tone: "sky",
    size: "lg"
  }), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 600
    }
  }, "Paulo Ferraz"), /*#__PURE__*/React.createElement("div", {
    className: "ds-caption"
  }, "Engenheiro de seguran\xE7a")))))));
}
function LessonScreen({
  go
}) {
  const [done, setDone] = React.useState(false);
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(PageHeader, {
    breadcrumb: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("a", {
      href: "#",
      onClick: e => {
        e.preventDefault();
        go('classroom');
      }
    }, "Sala de aula"), " / M\xF3dulo 1"),
    kicker: "Aula em v\xEDdeo",
    title: "Normas regulamentadoras",
    actions: /*#__PURE__*/React.createElement(Button, {
      variant: "secondary",
      icon: "arrow-left",
      onClick: () => go('classroom')
    }, "Voltar")
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'minmax(0,2fr) minmax(0,1fr)',
      gap: 'var(--space-6)',
      alignItems: 'start'
    }
  }, /*#__PURE__*/React.createElement(Card, null, /*#__PURE__*/React.createElement("div", {
    style: {
      aspectRatio: '16 / 9',
      borderRadius: 'var(--radius-md)',
      background: 'linear-gradient(135deg,var(--blue-100),var(--mint-100))',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center'
    }
  }, /*#__PURE__*/React.createElement(Button, {
    size: "lg",
    icon: "play",
    onClick: () => setDone(true)
  }, "Assistir aula")), /*#__PURE__*/React.createElement(Progress, {
    value: done ? 100 : 34,
    showLabel: true,
    caption: "Tempo assistido",
    variant: done ? 'success' : 'primary'
  }), /*#__PURE__*/React.createElement("p", {
    className: "ds-muted",
    style: {
      margin: 0
    }
  }, "O progresso \xE9 salvo automaticamente a cada 5 segundos. A aula \xE9 marcada como conclu\xEDda ap\xF3s 90% assistidos."), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 'var(--space-3)',
      alignItems: 'center',
      flexWrap: 'wrap'
    }
  }, done && /*#__PURE__*/React.createElement(Badge, {
    variant: "success"
  }, "Aula conclu\xEDda"), /*#__PURE__*/React.createElement(Button, {
    variant: done ? 'tonal' : 'success',
    icon: "check",
    onClick: () => setDone(true)
  }, done ? 'Concluída' : 'Marcar como concluída'), /*#__PURE__*/React.createElement(Button, {
    variant: "ghost",
    trailingIcon: "chevron-right",
    onClick: () => go('quiz')
  }, "Pr\xF3xima: prova do m\xF3dulo"))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 'var(--space-5)'
    }
  }, /*#__PURE__*/React.createElement(Card, {
    title: "Materiais da aula"
  }, D.materials.map(m => /*#__PURE__*/React.createElement("a", {
    key: m.name,
    href: "#",
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-3)',
      padding: 'var(--space-3) 0',
      borderTop: '1px solid var(--divider)',
      color: 'var(--text-primary)'
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "file-text",
    size: 20
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      flex: 1
    }
  }, m.name, /*#__PURE__*/React.createElement("span", {
    className: "ds-caption",
    style: {
      display: 'block'
    }
  }, m.size)), /*#__PURE__*/React.createElement(Icon, {
    name: "upload",
    size: 18
  })))), /*#__PURE__*/React.createElement(Card, {
    variant: "outlined",
    title: "Pr\xF3ximas aulas"
  }, D.modules[1].lessons.map(l => /*#__PURE__*/React.createElement("div", {
    key: l.id,
    className: "ds-body-sm",
    style: {
      padding: 'var(--space-3) 0',
      borderTop: '1px solid var(--divider)'
    }
  }, l.title))))));
}
function QuizScreen({
  go
}) {
  const [sent, setSent] = React.useState(false);
  return /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 'var(--reading-max)',
      margin: '0 auto'
    }
  }, /*#__PURE__*/React.createElement(PageHeader, {
    kicker: "Prova do m\xF3dulo 2",
    title: D.quiz.title,
    actions: /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        alignItems: 'center',
        gap: 'var(--space-3)'
      }
    }, /*#__PURE__*/React.createElement("span", {
      className: "ds-chip ds-chip-info ds-chip-lg"
    }, /*#__PURE__*/React.createElement(Icon, {
      name: "clock",
      size: 16
    }), " 19:42 restantes"))
  }), sent ? /*#__PURE__*/React.createElement(Alert, {
    variant: "success",
    title: "Tentativa enviada",
    action: /*#__PURE__*/React.createElement(Button, {
      variant: "tonal",
      onClick: () => go('classroom')
    }, "Voltar \xE0 sala de aula")
  }, "Sua nota preliminar \xE9 80%. A quest\xE3o dissertativa ser\xE1 corrigida por um gestor.") : /*#__PURE__*/React.createElement(Alert, {
    variant: "info",
    title: "Antes de come\xE7ar"
  }, D.quiz.instructions), /*#__PURE__*/React.createElement(Card, null, D.quiz.questions.map((q, i) => /*#__PURE__*/React.createElement("div", {
    key: q.id,
    style: {
      paddingTop: i ? 'var(--space-7)' : 0,
      marginTop: i ? 'var(--space-2)' : 0,
      borderTop: i ? '1px solid var(--divider)' : 'none'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      justifyContent: 'space-between',
      alignItems: 'center',
      gap: 'var(--space-3)',
      marginBottom: 'var(--space-3)'
    }
  }, /*#__PURE__*/React.createElement("span", {
    className: "ds-overline"
  }, "Quest\xE3o ", i + 1, " de ", D.quiz.questions.length), /*#__PURE__*/React.createElement(Badge, {
    variant: "outline",
    dot: false
  }, q.type)), /*#__PURE__*/React.createElement("h3", {
    style: {
      fontSize: 'var(--font-size-h4)',
      marginBottom: 'var(--space-4)'
    }
  }, q.text), q.options ? /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 'var(--space-3)'
    }
  }, q.options.map(o => /*#__PURE__*/React.createElement("label", {
    key: o,
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-4)',
      padding: 'var(--space-4) var(--space-5)',
      background: 'var(--surface-sunken)',
      border: '1.5px solid var(--border-color)',
      borderRadius: 'var(--radius-md)',
      cursor: 'pointer'
    }
  }, /*#__PURE__*/React.createElement("input", {
    type: q.type === 'Múltipla escolha' ? 'checkbox' : 'radio',
    name: 'q' + q.id,
    className: "ds-check-input"
  }), /*#__PURE__*/React.createElement("span", null, o)))) : /*#__PURE__*/React.createElement(Textarea, {
    name: 'essay' + q.id,
    label: "Sua resposta",
    rows: 5,
    hint: "M\xEDnimo de 200 caracteres."
  }))), /*#__PURE__*/React.createElement(FormActions, null, /*#__PURE__*/React.createElement(Button, {
    variant: "ghost",
    onClick: () => go('classroom')
  }, "Salvar e sair"), /*#__PURE__*/React.createElement(Button, {
    icon: "check",
    onClick: () => setSent(true)
  }, "Enviar prova"))));
}
function ForumScreen() {
  const [open, setOpen] = React.useState(false);
  const [topics, setTopics] = React.useState(D.topics);
  const [title, setTitle] = React.useState('');
  return /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      minHeight: 520
    }
  }, /*#__PURE__*/React.createElement(PageHeader, {
    kicker: "F\xF3rum de d\xFAvidas",
    title: "Fundamentos de Seguran\xE7a do Trabalho",
    subtitle: "Espa\xE7o da turma para d\xFAvidas, materiais e combinados."
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 'var(--space-4)'
    }
  }, topics.map(t => /*#__PURE__*/React.createElement(Card, {
    key: t.id,
    interactive: true
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 'var(--space-4)',
      alignItems: 'flex-start'
    }
  }, /*#__PURE__*/React.createElement(Avatar, {
    name: t.author,
    tone: t.pinned ? 'blue' : 'neutral'
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-3)',
      flexWrap: 'wrap'
    }
  }, t.pinned && /*#__PURE__*/React.createElement(Badge, {
    variant: "primary",
    dot: false
  }, "Fixado"), /*#__PURE__*/React.createElement("h3", {
    style: {
      fontSize: 'var(--font-size-h4)'
    }
  }, t.title)), /*#__PURE__*/React.createElement("p", {
    className: "ds-muted",
    style: {
      margin: '8px 0 0'
    }
  }, t.excerpt), /*#__PURE__*/React.createElement("div", {
    className: "ds-caption",
    style: {
      marginTop: 'var(--space-3)'
    }
  }, t.author, " \xB7 ", t.time)), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 8,
      color: 'var(--text-secondary)'
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "message-square",
    size: 18
  }), t.replies))))), /*#__PURE__*/React.createElement(Pagination, {
    current: 1,
    total: 3
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'sticky',
      bottom: 0,
      display: 'flex',
      justifyContent: 'flex-end',
      paddingTop: 'var(--space-6)'
    }
  }, /*#__PURE__*/React.createElement(Fab, {
    icon: "plus",
    label: "Novo t\xF3pico",
    onClick: () => setOpen(true)
  })), open && /*#__PURE__*/React.createElement(Modal, {
    title: "Novo t\xF3pico",
    onClose: () => setOpen(false),
    actions: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(Button, {
      variant: "ghost",
      onClick: () => setOpen(false)
    }, "Cancelar"), /*#__PURE__*/React.createElement(Button, {
      onClick: () => {
        if (title.trim()) setTopics([{
          id: Date.now(),
          title,
          author: D.user.name,
          excerpt: 'Aguardando primeiras respostas.',
          replies: 0,
          pinned: false,
          time: 'agora'
        }, ...topics]);
        setTitle('');
        setOpen(false);
      }
    }, "Publicar t\xF3pico"))
  }, /*#__PURE__*/React.createElement(Input, {
    name: "title",
    label: "T\xEDtulo do t\xF3pico",
    required: true,
    value: title,
    onChange: e => setTitle(e.target.value)
  }), /*#__PURE__*/React.createElement(Textarea, {
    name: "content",
    label: "Conte\xFAdo",
    rows: 5,
    required: true,
    hint: "Descreva sua d\xFAvida com o m\xE1ximo de contexto poss\xEDvel."
  })));
}
function SettingsScreen() {
  const [tab, setTab] = React.useState('org');
  return /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 900
    }
  }, /*#__PURE__*/React.createElement(PageHeader, {
    kicker: "Configura\xE7\xF5es",
    title: "Prefer\xEAncias da organiza\xE7\xE3o",
    subtitle: "Identidade aplicada aos certificados e regras gerais da plataforma."
  }), /*#__PURE__*/React.createElement(Tabs, {
    value: tab,
    onChange: setTab,
    style: {
      maxWidth: 520,
      marginBottom: 'var(--space-7)'
    },
    tabs: [{
      key: 'org',
      label: 'Organização'
    }, {
      key: 'notif',
      label: 'Notificações'
    }]
  }), tab === 'org' ? /*#__PURE__*/React.createElement(Card, null, /*#__PURE__*/React.createElement(FieldStack, {
    columns: 2
  }, /*#__PURE__*/React.createElement(Input, {
    name: "name",
    label: "Nome da organiza\xE7\xE3o",
    defaultValue: "Conselho Regional",
    required: true
  }), /*#__PURE__*/React.createElement(Input, {
    name: "cnpj",
    label: "CNPJ",
    defaultValue: "00.000.000/0001-00"
  }), /*#__PURE__*/React.createElement(Select, {
    name: "tz",
    label: "Fuso hor\xE1rio",
    options: ['America/Sao_Paulo', 'America/Manaus'],
    defaultValue: "America/Sao_Paulo"
  }), /*#__PURE__*/React.createElement(Input, {
    name: "signer",
    label: "Respons\xE1vel pela assinatura",
    defaultValue: "Dire\xE7\xE3o T\xE9cnica"
  })), /*#__PURE__*/React.createElement(Switch, {
    label: "Permitir valida\xE7\xE3o p\xFAblica de certificados",
    help: "Gera um hash SHA-256 e um QR Code por certificado emitido.",
    defaultChecked: true
  }), /*#__PURE__*/React.createElement(Switch, {
    label: "Exigir prova final para conclus\xE3o",
    defaultChecked: true
  }), /*#__PURE__*/React.createElement(FormActions, null, /*#__PURE__*/React.createElement(Button, {
    variant: "ghost"
  }, "Cancelar"), /*#__PURE__*/React.createElement(Button, {
    icon: "check"
  }, "Salvar altera\xE7\xF5es"))) : /*#__PURE__*/React.createElement(Card, null, /*#__PURE__*/React.createElement(Switch, {
    label: "Avisar quando uma matr\xEDcula for confirmada",
    defaultChecked: true
  }), /*#__PURE__*/React.createElement(Switch, {
    label: "Avisar sobre novas respostas no f\xF3rum",
    defaultChecked: true
  }), /*#__PURE__*/React.createElement(Switch, {
    label: "Resumo semanal por e-mail"
  }), /*#__PURE__*/React.createElement(FormActions, null, /*#__PURE__*/React.createElement(Button, {
    icon: "check"
  }, "Salvar prefer\xEAncias"))));
}
function PendingEssaysScreen() {
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(PageHeader, {
    kicker: "Acompanhamento",
    title: "Reda\xE7\xF5es pendentes",
    subtitle: "Quest\xF5es dissertativas aguardando corre\xE7\xE3o manual."
  }), /*#__PURE__*/React.createElement(DataTable, {
    headers: ['Aluno', 'Prova', 'Enviada em', 'Ações']
  }, D.essays.map(e => /*#__PURE__*/React.createElement("tr", {
    key: e.id
  }, /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-3)'
    }
  }, /*#__PURE__*/React.createElement(Avatar, {
    name: e.student,
    size: "sm",
    tone: "sky"
  }), e.student)), /*#__PURE__*/React.createElement("td", {
    className: "ds-muted"
  }, e.quiz), /*#__PURE__*/React.createElement("td", null, e.sent), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement(Button, {
    variant: "tonal",
    size: "sm",
    trailingIcon: "chevron-right"
  }, "Corrigir"))))));
}
Object.assign(window, {
  DashboardScreen,
  CoursesAdminScreen,
  MyCoursesScreen,
  ClassroomScreen,
  LessonScreen,
  QuizScreen,
  ForumScreen,
  SettingsScreen,
  PendingEssaysScreen
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/lms_app/screens.jsx", error: String((e && e.message) || e) }); }

// ui_kits/public_site/screens.jsx
try { (() => {
const NS = window.PlataformaEADDesignSystem_618bb2;
const {
  Button,
  Badge,
  Chip,
  Card,
  StatCard,
  Alert,
  Input,
  Checkbox,
  Switch,
  PageHeader,
  GuestPanel,
  HelpButton,
  Footer,
  Icon,
  Avatar,
  Progress,
  Table
} = NS;
const Nav = ({
  go
}) => /*#__PURE__*/React.createElement("header", {
  className: "ds-appbar",
  style: {
    background: 'transparent',
    boxShadow: 'none',
    maxWidth: 'var(--content-max)',
    margin: '0 auto',
    width: '100%'
  }
}, /*#__PURE__*/React.createElement("a", {
  href: "#",
  className: "ds-brand",
  onClick: e => {
    e.preventDefault();
    go('landing');
  }
}, /*#__PURE__*/React.createElement("span", {
  className: "ds-brand-mark"
}, "EAD"), "Plataforma EAD"), /*#__PURE__*/React.createElement("nav", {
  style: {
    display: 'flex',
    gap: 'var(--space-6)',
    alignItems: 'center'
  },
  className: "ds-body-sm"
}, /*#__PURE__*/React.createElement("a", {
  href: "#",
  onClick: e => e.preventDefault()
}, "Como funciona"), /*#__PURE__*/React.createElement("a", {
  href: "#",
  onClick: e => e.preventDefault()
}, "Para organiza\xE7\xF5es"), /*#__PURE__*/React.createElement("a", {
  href: "#",
  onClick: e => {
    e.preventDefault();
    go('verify');
  }
}, "Validar certificado")), /*#__PURE__*/React.createElement("div", {
  style: {
    display: 'flex',
    alignItems: 'center',
    gap: 'var(--space-3)'
  }
}, /*#__PURE__*/React.createElement(HelpButton, {
  title: "Ajuda",
  content: "Use o link de convite enviado pela sua organiza\xE7\xE3o para se matricular."
}), /*#__PURE__*/React.createElement(Button, {
  variant: "ghost",
  onClick: () => go('login')
}, "Entrar"), /*#__PURE__*/React.createElement(Button, {
  onClick: () => go('invite')
}, "Acessar convite")));
function LandingScreen({
  go
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      minHeight: '100vh',
      display: 'flex',
      flexDirection: 'column',
      background: 'var(--surface-body)'
    }
  }, /*#__PURE__*/React.createElement(Nav, {
    go: go
  }), /*#__PURE__*/React.createElement("main", {
    style: {
      flex: 1
    }
  }, /*#__PURE__*/React.createElement("section", {
    style: {
      maxWidth: 'var(--content-max)',
      margin: '0 auto',
      padding: 'var(--space-9) var(--space-7)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    className: "ds-hero",
    style: {
      display: 'grid',
      gridTemplateColumns: 'minmax(0,1.1fr) minmax(0,.9fr)',
      gap: 'var(--space-9)',
      alignItems: 'center'
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(Badge, {
    variant: "success",
    size: "lg"
  }, "Certificados com valida\xE7\xE3o p\xFAblica"), /*#__PURE__*/React.createElement("h1", {
    style: {
      fontSize: 'var(--font-size-display)',
      margin: 'var(--space-5) 0 var(--space-5)',
      maxWidth: '20ch'
    }
  }, "Capacita\xE7\xE3o t\xE9cnica continuada, do jeito certo"), /*#__PURE__*/React.createElement("p", {
    className: "ds-lead",
    style: {
      maxWidth: '52ch'
    }
  }, "Cursos, provas interativas e certificados oficiais em uma \xFAnica plataforma, pensada para organiza\xE7\xF5es que levam a forma\xE7\xE3o de suas equipes a s\xE9rio."), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 'var(--space-4)',
      marginTop: 'var(--space-7)',
      flexWrap: 'wrap'
    }
  }, /*#__PURE__*/React.createElement(Button, {
    size: "lg",
    onClick: () => go('login')
  }, "Acessar plataforma"), /*#__PURE__*/React.createElement(Button, {
    size: "lg",
    variant: "secondary",
    trailingIcon: "chevron-right",
    onClick: () => go('verify')
  }, "Validar um certificado")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 'var(--space-7)',
      marginTop: 'var(--space-8)',
      flexWrap: 'wrap'
    }
  }, [['3 perfis', 'Admin, gestor e aluno'], ['Multiorganização', 'Uma conta, várias instituições'], ['SHA-256', 'Hash único por certificado']].map(([a, b]) => /*#__PURE__*/React.createElement("div", {
    key: a
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--font-size-h4)',
      fontWeight: 800
    }
  }, a), /*#__PURE__*/React.createElement("div", {
    className: "ds-caption"
  }, b))))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 'var(--space-4)'
    }
  }, /*#__PURE__*/React.createElement(Card, {
    variant: "elevated",
    title: "Fundamentos de Seguran\xE7a",
    kicker: "Em andamento",
    meta: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("span", null, "13 aulas"), /*#__PURE__*/React.createElement("span", null, "4 horas"))
  }, /*#__PURE__*/React.createElement(Progress, {
    value: 62,
    showLabel: true,
    caption: "Progresso"
  })), /*#__PURE__*/React.createElement(Card, {
    variant: "elevated"
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-3)'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: 44,
      height: 44,
      borderRadius: '50%',
      background: 'var(--secondary-container)',
      color: 'var(--on-secondary-container)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center'
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "award",
    size: 22
  })), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 700
    }
  }, "Certificado emitido"), /*#__PURE__*/React.createElement("div", {
    className: "ds-caption"
  }, "Boas Pr\xE1ticas de Atendimento \xB7 12/07/2026")))), /*#__PURE__*/React.createElement(Card, {
    variant: "outlined"
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-3)'
    }
  }, /*#__PURE__*/React.createElement(Avatar, {
    name: "Ana Beatriz",
    size: "sm",
    tone: "blue"
  }), /*#__PURE__*/React.createElement("div", {
    className: "ds-body-sm",
    style: {
      flex: 1
    }
  }, "\u201CA prova do m\xF3dulo 2 vale nota?\u201D"), /*#__PURE__*/React.createElement(Badge, {
    variant: "info",
    dot: false
  }, "4")))))), /*#__PURE__*/React.createElement("section", {
    style: {
      maxWidth: 'var(--content-max)',
      margin: '0 auto',
      padding: '0 var(--space-7) var(--space-9)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      textAlign: 'center',
      marginBottom: 'var(--space-8)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    className: "ds-overline",
    style: {
      color: 'var(--primary)'
    }
  }, "Como funciona"), /*#__PURE__*/React.createElement("h2", {
    style: {
      fontSize: 'var(--font-size-h1)',
      marginTop: 'var(--space-3)'
    }
  }, "Do convite ao certificado, sem atrito")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(auto-fit,minmax(280px,1fr))',
      gap: 'var(--space-6)'
    }
  }, [['users', 'blue', 'Convite inteligente', 'A organização envia um link; quem já tem conta é matriculado sem duplicar cadastro.'], ['book', 'sky', 'Trilhas e aulas', 'Módulos com vídeo, PDF e leitura, com progresso salvo automaticamente.'], ['clipboard', 'mint', 'Provas e correção', 'Objetivas corrigidas na hora; dissertativas vão para a fila do gestor.'], ['award', 'blue', 'Certificado verificável', 'Emissão automática com hash SHA-256 e página pública de validação.']].map(([icon, tone, title, text]) => /*#__PURE__*/React.createElement(Card, {
    key: title
  }, /*#__PURE__*/React.createElement("span", {
    className: "ds-stat-icon",
    style: {
      background: tone === 'mint' ? 'var(--secondary-container)' : tone === 'sky' ? 'var(--tertiary-container)' : 'var(--primary-container)',
      color: tone === 'mint' ? 'var(--on-secondary-container)' : tone === 'sky' ? 'var(--on-tertiary-container)' : 'var(--on-primary-container)'
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: icon,
    size: 24
  })), /*#__PURE__*/React.createElement("h3", {
    style: {
      fontSize: 'var(--font-size-h4)'
    }
  }, title), /*#__PURE__*/React.createElement("p", {
    className: "ds-muted",
    style: {
      margin: 0
    }
  }, text))))), /*#__PURE__*/React.createElement("section", {
    style: {
      background: 'var(--blue-50)',
      padding: 'var(--space-9) var(--space-7)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 'var(--content-max)',
      margin: '0 auto',
      display: 'grid',
      gridTemplateColumns: 'repeat(auto-fit,minmax(220px,1fr))',
      gap: 'var(--space-5)'
    }
  }, /*#__PURE__*/React.createElement(StatCard, {
    icon: "buildings",
    tone: "blue",
    kicker: "Organiza\xE7\xF5es",
    value: "24",
    hint: "em produ\xE7\xE3o"
  }), /*#__PURE__*/React.createElement(StatCard, {
    icon: "users",
    tone: "sky",
    kicker: "Alunos ativos",
    value: "3.180",
    delta: "+8%"
  }), /*#__PURE__*/React.createElement(StatCard, {
    icon: "award",
    tone: "mint",
    kicker: "Certificados emitidos",
    value: "1.204",
    delta: "+12%"
  }), /*#__PURE__*/React.createElement(StatCard, {
    icon: "check",
    tone: "neutral",
    kicker: "Conclus\xE3o m\xE9dia",
    value: "67%"
  }))), /*#__PURE__*/React.createElement("section", {
    style: {
      maxWidth: 'var(--content-max)',
      margin: '0 auto',
      padding: 'var(--space-9) var(--space-7)'
    }
  }, /*#__PURE__*/React.createElement(Card, {
    variant: "elevated",
    style: {
      padding: 'var(--space-8)',
      display: 'grid',
      gridTemplateColumns: 'minmax(0,1fr) auto',
      gap: 'var(--space-7)',
      alignItems: 'center'
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("h2", {
    style: {
      fontSize: 'var(--font-size-h2)'
    }
  }, "Recebeu um convite?"), /*#__PURE__*/React.createElement("p", {
    className: "ds-lead",
    style: {
      margin: 'var(--space-3) 0 0',
      maxWidth: '54ch'
    }
  }, "Acesse o link enviado pela sua organiza\xE7\xE3o para se matricular no curso. Se j\xE1 tiver conta, \xE9 s\xF3 entrar.")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 'var(--space-3)',
      flexWrap: 'wrap'
    }
  }, /*#__PURE__*/React.createElement(Button, {
    size: "lg",
    onClick: () => go('invite')
  }, "Abrir convite"), /*#__PURE__*/React.createElement(Button, {
    size: "lg",
    variant: "ghost",
    onClick: () => go('login')
  }, "J\xE1 tenho conta"))))), /*#__PURE__*/React.createElement(Footer, {
    brand: "Plataforma EAD"
  }));
}
function LoginScreen({
  go
}) {
  const [err, setErr] = React.useState(false);
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      minHeight: '100vh'
    }
  }, /*#__PURE__*/React.createElement(GuestPanel, {
    brand: "Conselho Regional",
    mark: "CR"
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      display: 'flex',
      flexDirection: 'column',
      justifyContent: 'center',
      alignItems: 'center',
      position: 'relative',
      padding: 'var(--space-8)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      top: 'var(--space-6)',
      right: 'var(--space-6)'
    }
  }, /*#__PURE__*/React.createElement(HelpButton, {
    title: "Acesso",
    content: "Use o e-mail cadastrado pela sua organiza\xE7\xE3o. N\xE3o tem senha ainda? Use 'Esqueci minha senha'."
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      width: '100%',
      maxWidth: 'var(--form-max)'
    }
  }, err && /*#__PURE__*/React.createElement(Alert, {
    variant: "danger",
    title: "N\xE3o foi poss\xEDvel entrar",
    dismissable: true,
    onDismiss: () => setErr(false)
  }, "Verifique o e-mail e a senha e tente novamente."), /*#__PURE__*/React.createElement(PageHeader, {
    kicker: "Acesso",
    title: "Entrar na plataforma",
    subtitle: "Bem-vindo de volta. Continue de onde parou."
  }), /*#__PURE__*/React.createElement(Input, {
    name: "email",
    type: "email",
    label: "E-mail institucional",
    defaultValue: "joana.prado@conselho.br",
    required: true
  }), /*#__PURE__*/React.createElement(Input, {
    name: "password",
    type: "password",
    label: "Senha",
    defaultValue: "senha-secreta",
    required: true
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      marginBottom: 'var(--space-5)',
      flexWrap: 'wrap',
      gap: 'var(--space-3)'
    }
  }, /*#__PURE__*/React.createElement(Checkbox, {
    name: "remember",
    label: "Manter conectado",
    defaultChecked: true
  }), /*#__PURE__*/React.createElement("a", {
    href: "#",
    className: "ds-body-sm",
    style: {
      fontWeight: 700
    },
    onClick: e => {
      e.preventDefault();
      setErr(true);
    }
  }, "Esqueci minha senha")), /*#__PURE__*/React.createElement(Button, {
    block: true,
    size: "lg",
    onClick: () => go('app')
  }, "Entrar"), /*#__PURE__*/React.createElement("p", {
    className: "ds-caption",
    style: {
      textAlign: 'center',
      marginTop: 'var(--space-6)'
    }
  }, "Recebeu um convite? ", /*#__PURE__*/React.createElement("a", {
    href: "#",
    onClick: e => {
      e.preventDefault();
      go('invite');
    }
  }, "Concluir matr\xEDcula")))));
}
function InviteScreen({
  go
}) {
  const [sent, setSent] = React.useState(false);
  return /*#__PURE__*/React.createElement("div", {
    style: {
      minHeight: '100vh',
      background: 'var(--surface-body)'
    }
  }, /*#__PURE__*/React.createElement(Nav, {
    go: go
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 980,
      margin: '0 auto',
      padding: 'var(--space-8) var(--space-7) var(--space-9)',
      display: 'grid',
      gridTemplateColumns: 'minmax(0,1fr) minmax(0,1fr)',
      gap: 'var(--space-7)',
      alignItems: 'start'
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(PageHeader, {
    kicker: "Convite",
    title: "Confirme sua matr\xEDcula",
    subtitle: "Conselho Regional convidou voc\xEA para o curso Boas Pr\xE1ticas de Atendimento."
  }), sent && /*#__PURE__*/React.createElement(Alert, {
    variant: "success",
    title: "Matr\xEDcula confirmada"
  }, "Enviamos um e-mail com o link de acesso \xE0 plataforma."), /*#__PURE__*/React.createElement(Card, null, /*#__PURE__*/React.createElement(Input, {
    name: "name",
    label: "Nome completo",
    required: true
  }), /*#__PURE__*/React.createElement(Input, {
    name: "email",
    type: "email",
    label: "E-mail",
    required: true,
    hint: "Se j\xE1 existir uma conta com este e-mail, a matr\xEDcula \xE9 vinculada a ela."
  }), /*#__PURE__*/React.createElement(Input, {
    name: "cpf",
    label: "CPF",
    required: true
  }), /*#__PURE__*/React.createElement(Switch, {
    label: "Aceito receber avisos do curso por e-mail",
    defaultChecked: true
  }), /*#__PURE__*/React.createElement(Button, {
    block: true,
    size: "lg",
    icon: "check",
    onClick: () => setSent(true)
  }, "Confirmar matr\xEDcula"))), /*#__PURE__*/React.createElement(Card, {
    variant: "outlined",
    title: "Boas Pr\xE1ticas de Atendimento",
    kicker: "Sobre o curso",
    meta: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("span", null, "18 aulas"), /*#__PURE__*/React.createElement("span", null, "6 horas"), /*#__PURE__*/React.createElement("span", null, "Certificado incluso"))
  }, /*#__PURE__*/React.createElement("p", {
    className: "ds-muted",
    style: {
      margin: 0
    }
  }, "Comunica\xE7\xE3o, escuta ativa e registro de ocorr\xEAncias, com prova final e emiss\xE3o autom\xE1tica de certificado."), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 'var(--space-3)',
      marginTop: 'var(--space-2)'
    }
  }, ['Aulas em vídeo e materiais em PDF', 'Prova com 3 tentativas', 'Certificado com validação pública'].map(t => /*#__PURE__*/React.createElement("div", {
    key: t,
    style: {
      display: 'flex',
      gap: 'var(--space-3)',
      alignItems: 'center'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: 28,
      height: 28,
      borderRadius: '50%',
      background: 'var(--secondary-container)',
      color: 'var(--on-secondary-container)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center'
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "check",
    size: 16
  })), /*#__PURE__*/React.createElement("span", {
    className: "ds-body-sm"
  }, t)))))), /*#__PURE__*/React.createElement(Footer, {
    brand: "Plataforma EAD"
  }));
}
function VerifyScreen({
  go
}) {
  const [revoked, setRevoked] = React.useState(false);
  return /*#__PURE__*/React.createElement("div", {
    style: {
      minHeight: '100vh',
      background: 'var(--surface-body)',
      display: 'flex',
      flexDirection: 'column'
    }
  }, /*#__PURE__*/React.createElement(Nav, {
    go: go
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      maxWidth: 820,
      width: '100%',
      margin: '0 auto',
      padding: 'var(--space-8) var(--space-7) var(--space-9)'
    }
  }, /*#__PURE__*/React.createElement(PageHeader, {
    kicker: "Valida\xE7\xE3o p\xFAblica",
    title: "Certificado n\xBA 9f2b7c41",
    subtitle: "Confira os dados e a autenticidade do certificado emitido pela organiza\xE7\xE3o."
  }), revoked ? /*#__PURE__*/React.createElement(Alert, {
    variant: "danger",
    title: "Certificado revogado em 12/07/2026 14:20"
  }, "Motivo: emiss\xE3o em duplicidade identificada em auditoria interna.") : /*#__PURE__*/React.createElement(Alert, {
    variant: "success",
    title: "Certificado v\xE1lido"
  }, "Emitido por Conselho Regional e n\xE3o revogado."), /*#__PURE__*/React.createElement(Card, null, /*#__PURE__*/React.createElement(Table, {
    hoverable: false
  }, [['Aluno', 'Marina Duarte'], ['Curso', 'Fundamentos de Segurança do Trabalho'], ['Organização emissora', 'Conselho Regional'], ['Carga horária', '4 horas'], ['Data de emissão', '02/07/2026']].map(([k, v]) => /*#__PURE__*/React.createElement("tr", {
    key: k
  }, /*#__PURE__*/React.createElement("th", {
    scope: "row",
    style: {
      textTransform: 'none',
      letterSpacing: 0,
      fontWeight: 400,
      color: 'var(--text-secondary)',
      width: '38%'
    }
  }, k), /*#__PURE__*/React.createElement("td", {
    style: {
      fontWeight: 700
    }
  }, v)))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-4)',
      padding: 'var(--space-5)',
      background: 'var(--surface-sunken)',
      borderRadius: 'var(--radius-md)',
      marginTop: 'var(--space-5)'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: 44,
      height: 44,
      borderRadius: 12,
      background: 'var(--primary-container)',
      color: 'var(--on-primary-container)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      flex: 'none'
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "lock",
    size: 20
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      minWidth: 0
    }
  }, /*#__PURE__*/React.createElement("div", {
    className: "ds-overline"
  }, "Hash de valida\xE7\xE3o"), /*#__PURE__*/React.createElement("div", {
    className: "ds-caption",
    style: {
      wordBreak: 'break-all'
    }
  }, "9f2b7c41ae03d58e6b17c0a4d9e8f3b2c15d7a604e8b93f1c27a0d6b5e4f8912")))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 'var(--space-3)',
      marginTop: 'var(--space-6)',
      flexWrap: 'wrap'
    }
  }, /*#__PURE__*/React.createElement(Button, {
    variant: "tonal",
    icon: "file-text"
  }, "Baixar PDF"), /*#__PURE__*/React.createElement(Button, {
    variant: "secondary",
    onClick: () => setRevoked(r => !r)
  }, "Alternar estado revogado"), /*#__PURE__*/React.createElement(Button, {
    variant: "ghost",
    onClick: () => go('landing')
  }, "Voltar ao in\xEDcio"))), /*#__PURE__*/React.createElement(Footer, {
    brand: "Plataforma EAD"
  }));
}
Object.assign(window, {
  LandingScreen,
  LoginScreen,
  VerifyScreen,
  InviteScreen
});
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/public_site/screens.jsx", error: String((e && e.message) || e) }); }

__ds_ns.Avatar = __ds_scope.Avatar;

__ds_ns.Badge = __ds_scope.Badge;

__ds_ns.Button = __ds_scope.Button;

__ds_ns.Chip = __ds_scope.Chip;

__ds_ns.DeleteButton = __ds_scope.DeleteButton;

__ds_ns.Fab = __ds_scope.Fab;

__ds_ns.ICON_PATHS = __ds_scope.ICON_PATHS;

__ds_ns.Icon = __ds_scope.Icon;

__ds_ns.HelpButton = __ds_scope.HelpButton;

__ds_ns.NotificationsBell = __ds_scope.NotificationsBell;

__ds_ns.Card = __ds_scope.Card;

__ds_ns.DataTable = __ds_scope.DataTable;

__ds_ns.EmptyState = __ds_scope.EmptyState;

__ds_ns.Pagination = __ds_scope.Pagination;

__ds_ns.Progress = __ds_scope.Progress;

__ds_ns.StatCard = __ds_scope.StatCard;

__ds_ns.Table = __ds_scope.Table;

__ds_ns.Tabs = __ds_scope.Tabs;

__ds_ns.Alert = __ds_scope.Alert;

__ds_ns.ConfirmModal = __ds_scope.ConfirmModal;

__ds_ns.Modal = __ds_scope.Modal;

__ds_ns.Checkbox = __ds_scope.Checkbox;

__ds_ns.FieldStack = __ds_scope.FieldStack;

__ds_ns.FilterBar = __ds_scope.FilterBar;

__ds_ns.FormActions = __ds_scope.FormActions;

__ds_ns.Input = __ds_scope.Input;

__ds_ns.Select = __ds_scope.Select;

__ds_ns.Switch = __ds_scope.Switch;

__ds_ns.Textarea = __ds_scope.Textarea;

__ds_ns.Footer = __ds_scope.Footer;

__ds_ns.GuestPanel = __ds_scope.GuestPanel;

__ds_ns.PageHeader = __ds_scope.PageHeader;

__ds_ns.DEFAULT_SECTIONS = __ds_scope.DEFAULT_SECTIONS;

__ds_ns.Sidebar = __ds_scope.Sidebar;

__ds_ns.Topbar = __ds_scope.Topbar;

})();
