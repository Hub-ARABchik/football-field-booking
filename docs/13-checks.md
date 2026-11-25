# FFBS — санҷишҳои функсионалӣ ва техникӣ

## 1. Пешшартҳо

- PHP 8.1+ бо PDO MySQL; барои API-и кӯҳна инчунин `mbstring` тавсия мешавад.
- MySQL 5.7+ ё MariaDB-и мувофиқ; ҳамаи ҷадвалҳо InnoDB ва `utf8mb4`.
- Танзимоти махфӣ дар `config/config.php`.
- Барои насби тоза: `database/schema.sql`; барои базаи кӯҳна: нусхаи эҳтиётӣ ва мигратсияи `database/migrations/001_expand_existing_ffbs.sql`.
- API-и нав: `public/api/router.php`, масирҳо дар `routes/api.php`.

## 2. Санҷиши омодагии код

```bash
find src public/api routes -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/smoke.php
```

Ин фармон танҳо синтаксиси PHP-ро месанҷад; пайвасти MySQL ё дурустии ҳамаи қоидаҳои бизнесро тасдиқ намекунад.

## 3. Санҷиши API бо curl

Аввал CSRF token ва cookie гиред:

```bash
curl -i -c cookies.txt https://YOUR-DOMAIN/api/v2/auth/me
```

Қимати `csrf_token`-ро аз JSON гирифта, дар дархостҳои POST дар header-и `X-CSRF-Token` гузоред.

### Каталог ва дастрасӣ

```bash
curl -i 'https://YOUR-DOMAIN/api/v2/fields?page=1&per_page=12'
curl -i 'https://YOUR-DOMAIN/api/v2/fields?q=Хуҷанд&size=5x5'
curl -i 'https://YOUR-DOMAIN/api/v2/fields/1'
curl -i 'https://YOUR-DOMAIN/api/v2/fields/1/availability?week=0'
```

Интизорӣ: 200 барои майдончаи нашршуда; 404 барои ID-и номувофиқ; 422 барои параметри нодуруст.

### Бақайдгирӣ ва воридшавӣ

```bash
curl -i -b cookies.txt -c cookies.txt \
  -H 'Content-Type: application/json' -H 'X-CSRF-Token: CSRF_TOKEN' \
  -d '{"full_name":"Test Customer","phone":"992900000001","email":"test@example.com","password":"StrongPass123","account_type":"CUSTOMER"}' \
  https://YOUR-DOMAIN/api/v2/auth/register
```

Интизорӣ: 201; парол дар база танҳо ҳамчун password hash нигоҳ дошта мешавад. Телефон/email-и такрорӣ бояд 409 баргардонад.

### Брон ва бекоркунӣ

```bash
curl -i -b cookies.txt -H 'Content-Type: application/json' -H 'X-CSRF-Token: CSRF_TOKEN' \
  -d '{"field_id":1,"booking_date":"2026-12-01","start_hour":19,"duration_hours":1}' \
  https://YOUR-DOMAIN/api/v2/bookings
```

Интизорӣ: 201 барои соати холӣ; 409 барои соати банд; 422 барои санаи гузашта, давомнокии нодуруст ё вақти берун аз ҷадвал.

### Нақшҳо

```bash
curl -i -b owner-cookies.txt https://YOUR-DOMAIN/api/v2/owner/dashboard
curl -i -b customer-cookies.txt https://YOUR-DOMAIN/api/v2/admin/dashboard
```

Интизорӣ: OWNER ба панели соҳиби худ 200 мегирад; CUSTOMER ба панели ADMIN бояд 403 гирад. Барои санҷиши ADMIN, аввал ҳисоби алоҳидаи боэътимодро сабт карда, нақши онро дастӣ дар база таъин кунед; бақайдгирии оммавӣ набояд ADMIN эҷод кунад.

## 4. Матритсаи санҷишҳо

| ID | Санҷиш | Интизорӣ |
|---|---|---|
| FFBS-CHK-01 | Каталоги умумӣ | Танҳо `approved` ва `is_active=1` нишон дода мешавад |
| FFBS-CHK-02 | Ҷустуҷӯи `q` бо матни кириллӣ | Натиҷаҳо бо UTF-8 дуруст бармегарданд |
| FFBS-CHK-03 | Филтри `price_min > price_max` | 422; дархости SQL иҷро намешавад |
| FFBS-CHK-04 | Намоиши майдончаи pending | 404 барои корбари ҷамъиятӣ |
| FFBS-CHK-05 | Бақайдгирӣ бо телефон/email-и такрорӣ | 409; ҳисоби дуюм сохта намешавад |
| FFBS-CHK-06 | Пароли нодуруст | 401; ҷузъиёти мавҷудияти аккаунт ошкор намешавад |
| FFBS-CHK-07 | Дархости тағйирдиҳанда бе CSRF | 419; база тағйир намеёбад |
| FFBS-CHK-08 | CUSTOMER ба API-и OWNER/ADMIN | 403 |
| FFBS-CHK-09 | OWNER майдонча илова мекунад | `owner_id` ба ҳисоби ҷорӣ пайваст; `status=pending` |
| FFBS-CHK-10 | OWNER майдончаи шахси дигарро мехонад | Маълумоти соҳибони дигар баргардонда намешавад |
| FFBS-CHK-11 | Брон кардани вақт дар берун аз ҷадвал | 422; брон сохта намешавад |
| FFBS-CHK-12 | Ду дархост барои як фосилаи вақт | На бештар аз як брони фаъол; дархости дуюм 409 |
| FFBS-CHK-13 | Бронҳои ҳампӯш бо давомнокии гуногун | Конфликт бо санҷиши interval муайян мешавад |
| FFBS-CHK-14 | Бекор кардани брон аз ҷониби соҳиби дигар | 404; брон бетағйир мемонад |
| FFBS-CHK-15 | Бекоркунӣ дар дохили маҳдудияти 3 соат | 409; брон фаъол мемонад |
| FFBS-CHK-16 | Бекоркунӣ дар вақти иҷозатшуда | status=`cancelled`; сатри брон физикӣ нест намешавад |
| FFBS-CHK-17 | XSS дар номи майдонча/шарҳ | Ҳамаи қиматҳои намоишшаванда бо `htmlspecialchars` ё textContent бароварда мешаванд |
| FFBS-CHK-18 | SQL injection дар ҷустуҷӯ | Қимати воридшуда ҳамчун параметр қабул мешавад; SQL сохторӣ тағйир намеёбад |
| FFBS-CHK-19 | Ҳисоботи пардохт | Ҷамъбаст аз ҷадвали payments ҳисоб мешавад; бекоркунӣ пардохтро худкор “refund” намекунад |
| FFBS-CHK-20 | ADMIN пардохти pending-ро тасдиқ/рад мекунад | Тағйир танҳо барои ADMIN; audit_logs нав мешавад |
| FFBS-CHK-21 | Импорти дубораи schema | `CREATE TABLE IF NOT EXISTS` ҷадвалҳои мавҷударо нест намекунад; seed дубора такрор намешавад |

## 5. Санҷиши масирҳо бо curl

```bash
# Масири мавҷудбуда, вале бо HTTP method-и нодуруст бояд 405 диҳад.
curl -i -X DELETE 'https://YOUR-DOMAIN/api/v2/fields'

# Масири номавҷуд бояд 404 диҳад.
curl -i 'https://YOUR-DOMAIN/api/v2/does-not-exist'

# ID-и ғайриададӣ барои параметри {id} набояд ба контроллер гузарад.
curl -i 'https://YOUR-DOMAIN/api/v2/fields/not-an-id'
```

Интизории HTTP: 405 барои method-и нодуруст, 404 барои route ё ID-и нодуруст.

## 6. Эзоҳҳои муҳим

- Ин ҳуҷҷат **нақшаи санҷиш** аст, на далели иҷро шудани санҷишҳо дар сервери production.
- Барои санҷиши concurrency ду сессия/раванди алоҳида истифода баред.
- Пеш аз мигратсия backup гиред ва онро дар базаи санҷишӣ барқарор кунед.
- Паролҳои demo-ро ба файли seed нагузоред.
- `database/database.sql`-и кӯҳна ҳангоми иҷро DROP TABLE мекунад; онро барои навсозии базаи воқеӣ истифода набаред.
