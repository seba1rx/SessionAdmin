# Plan: SessionAdmin ↔ TabManager integration

> Status legend: `[ ]` pendiente · `[x]` completado · `[-]` descartado/no aplica

---

## Estado actual de los proyectos

### seba1rx/tabmanager — post-refactor

```
src/
  Contracts/
    SessionStoreInterface.php   ← NEW: contrato de sesión (6 métodos)
  PhpSessionStore.php           ← NEW: implementación por defecto (wrappea $_SESSION)
  TabManager.php                ← REFACTORED: constructor injection, store abstraction
Exceptions/
  TabManagerException.php
bin/bootstrap.php               (sin cambios — usa new TabManager() con default store)
assets/seba1rx_tabmanagerclient.js
tests/TabManagerTest.php        68 tests (ArraySessionStore como test double)
```

**API pública actual de TabManager:**

| Método | Estado |
|---|---|
| `__construct(?SessionStoreInterface $store = null)` | ✅ refactorizado |
| `indexNewTab(string $tabId): void` | ✅ |
| `touchTab(string $tabId): void` | ✅ |
| `set(string $key, mixed $value): void` | ✅ |
| `get(string $key, mixed $default = null): mixed` | ✅ |
| `isTabIndexed(?string $tabId = null): bool` | ✅ agregado |
| `markInactiveTab(string $tabId): void` | ✅ |
| `destroyTabSession(string $tabId): void` | ✅ |
| `cleanupInactiveTabs(int $olderThanSeconds): int` | ✅ agregado |
| `static isValidTabId(string $id): bool` | ✅ |
| `getTabIdStrict(): ?string` | ✅ |
| `debug(): array` | ✅ |

**Lo que NO tiene aún:**
- No implementa ninguna interfaz externa
- No tiene `seba1rx/sessionadmin` como dependencia

---

## Arquitectura objetivo

```
seba1rx/sessionadmin                    seba1rx/tabmanager
────────────────────────                ──────────────────────────────────
Contracts/                              require: seba1rx/sessionadmin
  SessionInterface                      
  TabHandlerInterface  ◄──────────────  class TabManager implements TabHandlerInterface
                                        
Session (abstract)                      Contracts/
SessionAdmin (abstract)                   SessionStoreInterface  (contrato interno)
  $tabHandler: ?TabHandlerInterface       
  $autoCleanupTabs: int               PhpSessionStore (implementación por defecto)
  setTabHandler()                     bootstrap.php   (autoload.files)
  activateSession()                   assets/seba1rx_tabmanagerclient.js
```

**Dependencia:** unidireccional `tabmanager → sessionadmin`. SessionAdmin nunca conoce la clase `TabManager`, solo type-hintea contra `TabHandlerInterface`.

---

## Cómo se comparte la sesión PHP (sin adapter)

El `PhpSessionStore` de tabmanager ya maneja el caso de integración correctamente:

```
$session->activateSession()
  └─ session_start() ←── sesión iniciada

new TabManager()
  └─ PhpSessionStore::start()
       └─ session_status() === PHP_SESSION_ACTIVE → no-op ✓

$_SESSION
  ├── 'sessionadmin'  →  gestionado por SessionAdmin
  └── 'tabmanager'    →  gestionado por TabManager
```

No se necesita ningún adapter. Los namespaces en `$_SESSION` son distintos y no colisionan. El mismo `$_SESSION` es accesible para ambos porque PHP lo expone globalmente.

### ¿Para qué existe entonces `SessionStoreInterface` en tabmanager?

Para que TabManager sea testeable sin PHP sessions reales (los tests usan `ArraySessionStore`) y para que cualquier consumidor —incluyendo futuros implementadores— pueda inyectar su propio backend de sesión. No se necesita un adapter de sessionadmin porque la sesión ya es compartida vía `$_SESSION`.

---

## Fase 1 — Definir `TabHandlerInterface` en sessionadmin ✅

**Archivo creado:** `src/Contracts/TabHandlerInterface.php`

Los métodos deben coincidir exactamente con las firmas ya existentes en `TabManager`:

```php
namespace Seba1rx\SessionAdmin\Contracts;

interface TabHandlerInterface
{
    public function indexNewTab(string $tabId): void;
    public function touchTab(string $tabId): void;
    public function set(string $key, mixed $value): void;
    public function get(string $key, mixed $default = null): mixed;
    public function isTabIndexed(?string $tabId = null): bool;
    public function markInactiveTab(string $tabId): void;
    public function destroyTabSession(string $tabId): void;
    public function cleanupInactiveTabs(int $olderThanSeconds): int;
}
```

Todos los métodos deben tener docblock con `@param`, `@return` y descripción de comportamiento.

### Tareas

- [x] Crear `src/Contracts/TabHandlerInterface.php`
- [x] El namespace PSR-4 ya cubre `src/Contracts/` — no requiere cambios en composer.json

---

## Fase 2 — Integrar el handler en `SessionAdmin` ✅

**Archivo modificado:** `src/SessionAdmin.php`

### Propiedades a agregar

```php
/** @var TabHandlerInterface|null Handler de tabs inyectado vía setTabHandler(). */
public ?TabHandlerInterface $tabHandler = null;

/**
 * Segundos tras los cuales los tabs inactivos se eliminan automáticamente
 * en cada llamada a activateSession(). 0 = desactivado (default).
 * Requiere que $tabHandler esté configurado.
 * @var int
 */
public int $autoCleanupTabs = 0;
```

### Método a agregar

```php
/**
 * Inyecta un handler de tabs. Debe llamarse antes de activateSession().
 * Si $autoCleanupTabs > 0, el handler se usará para limpiar tabs inactivos
 * en cada llamada a activateSession().
 *
 * @param TabHandlerInterface $handler
 * @return void
 */
public function setTabHandler(TabHandlerInterface $handler): void
{
    $this->tabHandler = $handler;
}
```

### Cambio en `activateSession()`

Al final, antes del loop de `$this->keys`:

```php
if ($this->tabHandler !== null && $this->autoCleanupTabs > 0) {
    $this->tabHandler->cleanupInactiveTabs($this->autoCleanupTabs);
}
```

### Uso desde la aplicación

```php
// En el entry point (index.php, bootstrap, etc.)
$session = new MySession();
$session->setTabHandler(new \Seba1rx\TabManager\TabManager());
$session->autoCleanupTabs = 30; // opcional: limpiar tabs inactivos > 30 s
$session->activateSession();

// En cualquier endpoint posterior:
$session->tabHandler->set('cart', ['item' => 1]);
$cart  = $session->tabHandler->get('cart');
$ready = $session->tabHandler->isTabIndexed(); // false hasta que el JS registre el tab
```

### Tareas

- [x] Agregar `use Seba1rx\SessionAdmin\Contracts\TabHandlerInterface;` en `SessionAdmin.php`
- [x] Agregar propiedad `$tabHandler`
- [x] Agregar propiedad `$autoCleanupTabs`
- [x] Agregar método `setTabHandler()`
- [x] Agregar bloque de cleanup en `activateSession()`
- [x] Actualizar docblock de `activateSession()` mencionando el cleanup condicional

---

## Fase 3 — Tests en sessionadmin ✅

**Archivo modificado:** `tests/SessionAdminTest.php` — 58 tests, 113 assertions, todo verde

Los tests que involucren `TabHandlerInterface` deben usar un mock de PHPUnit para no depender de la clase `TabManager` concreta.

### Tests agregados (todos verdes)

- [x] `testTabHandlerDefaultIsNull()` — `$tabHandler` es `null` por defecto
- [x] `testAutoCleanupTabsDefaultIsZero()` — `$autoCleanupTabs` es `0` por defecto
- [x] `testSetTabHandlerAssignsHandler()` — usa `createStub()` (sin expectativas de PHPUnit 13)
- [x] `testActivateSessionCallsCleanupWhenHandlerSetAndAutoCleanupEnabled()` — mock espera `cleanupInactiveTabs(30)`
- [x] `testActivateSessionSkipsCleanupWhenAutoCleanupIsZero()` — mock nunca recibe `cleanupInactiveTabs()`
- [x] `testActivateSessionSkipsCleanupWhenNoHandlerSet()` — `$tabHandler === null`, sin error

---

## Fase 4 — Documentación en sessionadmin ✅

### `CLAUDE.md`

- [x] Agregar `src/Contracts/TabHandlerInterface.php` en la sección de arquitectura (class hierarchy)
- [x] Documentar `$tabHandler`, `$autoCleanupTabs`, `setTabHandler()` en la sección de SessionAdmin
- [x] Agregar entrada en la tabla de Configuration properties para `$autoCleanupTabs`
- [x] Agregar ejemplo de integración con TabManager en "Implementing the package"

### `README.md`

- [x] Agregar sección "Tab isolation (via seba1rx/tabmanager)" con ejemplo de wiring
- [x] Actualizar tabla de API pública para incluir `setTabHandler()`
- [x] Actualizar tabla de Contracts para incluir `TabHandlerInterface`

---

## Fase 5 — Completar tabmanager: clase bridge `SessionAdminBridge` ✅

**Diseño adoptado:** Bridge pattern. `TabManager` no depende de sessionadmin. tabmanager expone
`SessionAdminBridge` (en `src/Bridge/`) que extiende `TabManager` e implementa `TabHandlerInterface`.
El consumer que usa ambos packages instancia el bridge en lugar de `TabManager` directamente.

### Cambios realizados en tabmanager

- [x] `$store` cambiado de `private` a `protected` en `TabManager` (cambio no-breaking: additive)
- [x] Creado `src/Bridge/SessionAdminBridge.php` — extiende `TabManager`, implementa `TabHandlerInterface`
  - Constructor NO llama `$this->store->start()` — SessionAdmin es dueño del ciclo de vida de la sesión
  - PSR-4: cubierto por el mapping existente `"Seba1rx\\TabManager\\": "src/"`
  - Solo se carga si el consumer lo usa — sin errores si sessionadmin no está instalado
- [x] Agregado `suggest` en `composer.json`: `"seba1rx/sessionadmin": "Provides TabHandlerInterface..."`
- [x] Actualizado README.md — sección "Integration with seba1rx/sessionadmin"
- [x] Actualizado llms.txt — sección "Integration with seba1rx/sessionadmin"
- [x] 67 tests, 114 assertions — todo verde tras el cambio de visibilidad

### Uso desde el consumer

```php
use Seba1rx\TabManager\Bridge\SessionAdminBridge;

$session = new App\MySession();
$session->setTabHandler(new SessionAdminBridge());
$session->autoCleanupTabs = 30;
$session->activateSession(); // configura nombre y lifetime de sesión, luego la inicia

$session->tabHandler->set('cart', $items);
$cart = $session->tabHandler->get('cart');
```

### Nota: endpoints de bootstrap con nombre de sesión personalizado

`bin/bootstrap.php` crea `new TabManager()` para los endpoints `/tabmanager/*`. Si SessionAdmin
usa un nombre de sesión personalizado (ej. `$this->sessionName = 'my_app'`), esos endpoints deben
configurar el mismo nombre antes de `require 'vendor/autoload.php'`, o enrutarse a través de un
script que llame `$session->activateSession()` primero.

---

## Fase 6 — Verificación de integración end-to-end

- [ ] `composer test` en sessionadmin → todo verde
- [ ] `composer test` en tabmanager → todo verde
- [ ] Smoke test manual: instanciar ambos en un script PHP, verificar que comparten `$_SESSION` sin conflictos
- [ ] Actualizar un demo (SPA o MPA) para mostrar el wiring completo

---

## Orden de ejecución

```
1. Fase 1  — crear TabHandlerInterface en sessionadmin
2. Fase 2  — SessionAdmin: setTabHandler, $tabHandler, $autoCleanupTabs
3. Fase 3  — tests de integración en sessionadmin
4. composer test en sessionadmin (todo verde antes de continuar)
5. Fase 4  — docs sessionadmin (CLAUDE.md + README.md)
6. Fase 5  — tabmanager: implements + composer.json + test
7. composer test en tabmanager (todo verde)
8. Fase 6  — verificación cruzada e2e
```
