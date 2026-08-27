import os
import glob
import re
import json

# Словарь переводов и понятных описаний смысловых корней иконок
TRANSLATIONS = {
    'accountantconsultation': 'Консультация бухгалтера',
    'accountcontrol': 'Контроль счетов',
    'accountgos': 'Гос. счёт',
    'accountrub': 'Рублевый счёт',
    'accountusd': 'Валютный счёт (USD)',
    'accounttorg': 'Торговый счёт',
    'accountsaccess': 'Доступ к счетам',
    'accounts': 'Счета и аккаунты',
    'account': 'Учетная запись / Счёт',
    'acredetivesinkasso': 'Аккредитивы и инкассо',
    'adaptiveclosedsideoverlay': 'Закрытие боковой панели',
    'aiagents': 'ИИ Агенты / Нейросети',
    'airbnb': 'Airbnb',
    'akbars': 'Ак Барс Банк',
    'alibaba': 'Alibaba',
    'aliexpress': 'AliExpress',
    'alphabank': 'Альфа-Банк',
    'amarker': 'Маркер A',
    'amazonaws': 'AWS Cloud',
    'amendmentscontract': 'Изменения в договоре',
    'amex': 'American Express',
    'analytanalytics': 'Аналитика и отчёты',
    'analyticsforbusiness': 'Бизнес-аналитика',
    'answer': 'Ответ / Сообщение',
    'anygoals': 'Любые цели',
    'anywash': 'Автомойки AnyWash',
    'api': 'API / Интеграция',
    'applepay': 'Apple Pay',
    'apple': 'Apple',
    'archive': 'Архив / Документы',
    'arrowcircleright': 'Стрелка вправо в круге',
    'arrowdown': 'Стрелка вниз',
    'attachment': 'Вложение / Прикрепить файл',
    'avangard': 'Банк Авангард',
    'avia': 'Авиабилеты / Перелёты',
    'b2b': 'B2B переводы / Сервисы',
    'back': 'Назад',
    'backwardness': 'Отставание / Просрочка',
    'balance': 'Баланс / Остаток',
    'bankingsupport': 'Банковское сопровождение',
    'bankpercent': 'Процентная ставка',
    'banksaintpetersburg': 'Банк Санкт-Петербург',
    'barcharthorizontal': 'Горизонтальная диаграмма',
    'barchartvertical': 'Вертикальная диаграмма',
    'barchart': 'Столбчатая диаграмма',
    'bcard': 'Бизнес-карта',
    'bell': 'Уведомления / Колокольчик',
    'benefitty': 'Льготы и бонусы',
    'bestplace': 'Лучшее место / Локация',
    'betaacquiring': 'Эквайринг (Бета)',
    'billpayment': 'Оплата счетов',
    'bill': 'Счёт на оплату / Чек',
    'blind': 'Для слабовидящих',
    'bmarker': 'Маркер B',
    'booking': 'Бронирование / Booking',
    'bookkeeperblack': 'Главный бухгалтер',
    'bookkeeperforbusiness': 'Бухгалтерия для бизнеса',
    'bookkeeperforip': 'Бухгалтерия для ИП',
    'bookkeeperoutsource': 'Бухгалтерский аутсорсинг',
    'bookkeeperportal': 'Портал бухгалтера',
    'bookkeeperyellow': 'Бухгалтер',
    'bookkeeper': 'Бухгалтерия',
    'bringafriend': 'Приведи друга / Рефералы',
    'brokers': 'Брокерские услуги',
    'businessenvironment': 'Бизнес-среда',
    'businessguide': 'Бизнес-гид / Инструкция',
    'businessprofile': 'Профиль бизнеса',
    'businesstravel': 'Командировки / Деловой туризм',
    'buttononlinechat': 'Кнопка онлайн-чата',
    'buyequipment': 'Покупка оборудования',
    'buyofgoods': 'Закупка товаров',
    'buyrealestate': 'Покупка недвижимости',
    'calendarpercent': 'Календарь скидок / процентов',
    'calendar': 'Календарь / Дата',
    'callcenter': 'Колл-центр / Поддержка',
    'cardbusinessmir': 'Бизнес-карта МИР',
    'cardbusiness': 'Бизнес-карта',
    'cardcreditmastercard': 'Кредитная карта Mastercard',
    'cardcreditmir': 'Кредитная карта МИР',
    'cardcreditvisa': 'Кредитная карта Visa',
    'cardcredit': 'Кредитная карта',
    'carddebitmastercard': 'Дебетовая карта Mastercard',
    'carddebitmir': 'Дебетовая карта МИР',
    'carddebitvisa': 'Дебетовая карта Visa',
    'carddigitalmastercard': 'Цифровая карта Mastercard',
    'carddigitalvisa': 'Цифровая карта Visa',
    'cardguard': 'Защита карт',
    'cardmomentummastercard': 'Карта Momentum Mastercard',
    'cardmomentumvisa': 'Карта Momentum Visa',
    'cardpremiummastercard': 'Премиальная карта Mastercard',
    'cardpremiummir': 'Премиальная карта МИР',
    'cardpremiumvisa': 'Премиальная карта Visa',
    'cardvisa': 'Карта Visa',
    'card': 'Банковская карта',
    'caretdown': 'Стрелка разворачивания вниз',
    'caretleft': 'Стрелка влево',
    'caretright': 'Стрелка вправо',
    'caretup': 'Стрелка разворачивания вверх',
    'cargosearch': 'Поиск грузов / Логистика',
    'carouselleft': 'Карусель влево',
    'carouselright': 'Карусель вправо',
    'cashback': 'Кэшбэк / Возврат денег',
    'cashintransit': 'Деньги в пути / Инкассация',
    'cashorder': 'Заказ наличных',
    'cash': 'Наличные деньги',
    'certifcate': 'Сертификат / Лицензия',
    'certificatebasic': 'Базовый сертификат',
    'certificateexpress': 'Экспресс-сертификат',
    'certificatequalified': 'Квалифицированный сертификат ЭЦП',
    'certificate': 'Сертификат ЭЦП',
    'cfa': 'Цифровые финансовые активы (ЦФА)',
    'changeaccount': 'Смена аккаунта / счёта',
    'chatdocument': 'Документ в чате',
    'chatdownload': 'Скачать из чата',
    'chatimage': 'Изображение в чате',
    'chatoperatordocument': 'Документ оператора',
    'chatoperatordownload': 'Скачивание оператора',
    'chatoperatorimage': 'Картинка оператора',
    'chatoperatorotherfiles': 'Файлы оператора',
    'chatotherfiles': 'Прочие файлы чата',
    'chat': 'Чат / Диалог',
    'checkboxbulk': 'Массовый чекбокс',
    'checkboxtick': 'Чекбокс отмечен',
    'checkemployee': 'Проверка сотрудников',
    'chipscaretdown': 'Фильтр-чип со стрелкой вниз',
    'chipscaretup': 'Фильтр-чип со стрелкой вверх',
    'clear': 'Очистить / Сбросить',
    'clientscomment': 'Отзыв / Комментарий клиента',
    'clock': 'Время / Часы',
    'close': 'Закрыть / Отмена',
    'closelarge': 'Закрыть (крупная)',
    'closemedium': 'Закрыть (средняя)',
    'closenotification': 'Закрыть уведомление',
    'closetooltip': 'Закрыть подсказку',
    'clouddragupload': 'Перетаскивание в облако',
    'cloudsign': 'Облачная подпись ЭЦП',
    'cloud': 'Облачный сервис / Хранилище',
    'cmarker': 'Маркер C',
    'comment': 'Комментарий / Заметка',
    'company': 'Компания / Организация',
    'compensate': 'Компенсация',
    'compensation': 'Выплаты и компенсации',
    'connectionrestored': 'Соединение восстановлено',
    'connectionweak': 'Слабый сигнал сети',
    'constructionandrepair': 'Строительство и ремонт',
    'constructioncommercial': 'Коммерческое строительство',
    'constructionfutureprofits': 'Инвестиции в строительство',
    'constructionhomeequity': 'Долевое строительство',
    'constructionwithescrow': 'Строительство с эскроу',
    'constructionwithoutescrow': 'Строительство без эскроу',
    'consultation': 'Консультация эксперта',
    'contractexamination': 'Экспертиза договоров',
    'cooperatives': 'Кооперативы',
    'copy': 'Копировать',
    'counterpartyselection': 'Подбор контрагентов',
    'counterparty': 'Контрагент / Партнер',
    'create': 'Создать / Добавить',
    'creatingroutes': 'Создание маршрутов',
    'creditcard': 'Кредитная карта',
    'creditforpayments': 'Кредит на платежи',
    'credithistory': 'Кредитная история',
    'creditinsurance': 'Страхование кредита',
    'creditmonitoring': 'Кредитный мониторинг',
    'creditpotential': 'Кредитный потенциал',
    'credits': 'Кредиты и займы',
    'credituralbank': 'Кредит Урал Банк',
    'crm': 'CRM система / Клиенты',
    'currencyaccountcny': 'Счёт в юанях (CNY)',
    'currencyaccountdirham': 'Счёт в дирхамах (AED)',
    'currencyaccountdollar': 'Счёт в долларах (USD)',
    'currencyaccounteuro': 'Счёт в евро (EUR)',
    'currencyaccountgbr': 'Счёт в фунтах (GBP)',
    'currencyaccountmanat': 'Счёт в манатах (AZN)',
    'currencyaccountrial': 'Счёт в риалах (IRR)',
    'currencyaccountru': 'Счёт в рублях (RUB)',
    'currencyaccountrupee': 'Счёт в рупиях (INR)',
    'currencyaccountsomoni': 'Счёт в сомони (TJS)',
    'currencyaccountunique': 'Уникальный валютный счёт',
    'currencypayment': 'Валютный платёж',
    'currency': 'Валюта / Обмен',
    'customsguarantee': 'Таможенная гарантия',
    'customs': 'Таможня / ВЭД',
    'cybersecurity': 'Кибербезопасность',
    'darktheme': 'Тёмная тема',
    'dashboardsettings': 'Настройки дашборда',
    'deaf': 'Для слабослышащих',
    'defaulticon': 'Иконка по умолчанию',
    'delete': 'Удалить / Корзина',
    'delivery': 'Доставка / Курьер',
    'deposit': 'Депозит / Вклад',
    'deregistrationcontract': 'Снятие контракта с учёта',
    'deriatives': 'Деривативы',
    'derivatives': 'Финансовые деривативы',
    'dialognew': 'Новый диалог',
    'dialogsall': 'Все диалоги / Сообщения',
    'digitalarchive': 'Цифровой архив',
    'digitalfactoring': 'Цифровой факторинг',
    'digitalruble': 'Цифровой рубль',
    'disabled': 'Отключено / Недоступно',
    'dislikeempty': 'Дизлайк (контур)',
    'dislikefilled': 'Дизлайк (закрашенный)',
    'dislike': 'Не нравится / Дизлайк',
    'distressedassets': 'Проблемные активы',
    'distribute': 'Распределить',
    'dmarker': 'Маркер D',
    'docdoc': 'СберЗдоровье (DocDoc)',
    'documentconstructor': 'Конструктор документов',
    'document': 'Документ / Файл',
    'donutchart': 'Круговая диаграмма',
    'download': 'Скачать / Загрузить',
    'dragdrop': 'Перетаскивание',
    'dropdownwhite': 'Выпадающее меню (белое)',
    'dropupwhite': 'Сворачивание меню (белое)',
    'edittext': 'Редактировать текст',
    'edit': 'Редактировать / Карандаш',
    'einv': 'Электронное инвойсирование',
    'electronics': 'Электроника',
    'emergencymode': 'Аварийный режим',
    'employeeprotect': 'Защита сотрудников',
    'emptytable': 'Пустая таблица',
    'encashment': 'Инкассация',
    'entrepreneur': 'Предприниматель / ИП',
    'entrywhite': 'Вход (белый)',
    'entry': 'Вход / Авторизация',
    'error': 'Ошибка / Опасность',
    'escrow': 'Эскроу-счёт',
    'evotorcloud': 'Эвотор Облако',
    'evotor': 'Смарт-терминалы Эвотор',
    'exchangecurrency': 'Обмен валюты',
    'exchangemetals': 'Драгоценные металлы',
    'executionpaymentorder': 'Исполнение платёжного поручения',
    'exit': 'Выход / Вылогиниться',
    'export': 'Экспорт данных',
    'eyeshearts': 'Влюбленные глаза / Восторг',
    'factoring': 'Факторинг',
    'filecsv': 'Файл CSV',
    'filedoc': 'Файл Word / DOC',
    'fileonec': 'Файл 1С',
    'filepdf': 'Файл PDF',
    'filexls': 'Файл Excel / XLS',
    'filexml': 'Файл XML',
    'filteroff': 'Фильтр выключен',
    'filteron': 'Фильтр включен',
    'fines': 'Штрафы и пени',
    'finhelper': 'Финансовый помощник',
    'finmonitoring': 'Финмониторинг (115-ФЗ)',
    'food': 'Питание / Общепит',
    'fuelcard': 'Топливная карта',
    'fuelup': 'Заправка / Топливо',
    'furthernotice': 'Уведомление',
    'gazprom': 'Газпромбанк',
    'gigaassistaint': 'GigaAssistant / Ассистент',
    'gigachat': 'Нейросеть GigaChat',
    'givelife': 'Благотворительность (Подари жизнь)',
    'googlepay': 'Google Pay',
    'google': 'Google',
    'gosoboronzakaz': 'Гособоронзаказ (ГОЗ)',
    'guarantbusiness': 'Гарантии бизнесу',
    'guarantee': 'Банковская гарантия',
    'guard': 'Охрана / Защита',
    'hardonseeing': 'Для слабовидящих',
    'headerkebab': 'Меню в шапке (три точки)',
    'help': 'Помощь / Справка',
    'hidden': 'Скрытый объект',
    'hint': 'Подсказка / Справка',
    'homebank': 'Home Bank',
    'homecreditbank': 'Хоум Кредит Банк',
    'horeca': 'HoReCa (Рестораны/Отели)',
    'houseforsale': 'Продажа недвижимости',
    'image': 'Картинка / Изображение',
    'importexport': 'Импорт и Экспорт',
    'import': 'Импорт',
    'individual': 'Физлицо / Гражданин',
    'info': 'Информация / Инфо',
    'insuranceconstructionrisk': 'Страхование строительных рисков',
    'insuranceforsellers': 'Страхование продавцов',
    'insuranceusd': 'Страхование в валюте',
    'insurancevisitor': 'Страхование мигрантов/гостей',
    'insurance': 'Страхование',
    'integratedservices': 'Комплексные услуги',
    'internationalonlinebusinessmission': 'Бизнес-миссии за рубежом',
    'internetacquiring': 'Интернет-эквайринг',
    'internet': 'Интернет',
    'invisible': 'Скрыто / Глаз перечеркнут',
    'invoicerub': 'Счёт в рублях',
    'itservice': 'IT-услуги / Поддержка',
    'jcb': 'Карта JCB',
    'jivochat': 'JivoChat',
    'journal': 'Журнал / Логи',
    'joysdigital': 'Цифровые сервисы Joys',
    'kebab': 'Меню Три точки',
    'kuper': 'Купер (СберМаркет)',
    'labelnew': 'Метка NEW',
    'labelpromo': 'Метка Промо',
    'labelrecommend': 'Метка Рекомендуем',
    'lamoda': 'Lamoda',
    'langselectcn': 'Китайский язык (CN)',
    'langselecten': 'Английский язык (EN)',
    'langselectru': 'Русский язык (RU)',
    'lawyer': 'Юрист / Юридические услуги',
    'legaldossieredit': 'Редактирование правового досье'
}

# Категории иконок по суффиксам SberBusiness UI Design System
CATEGORIES = {
    'Prd': 'Продукты и Сервисы',
    'Prdx': 'Продукты (Расширенные)',
    'Srv': 'Системные функции и Интерфейс',
    'Srvx': 'Элементы интерфейса',
    'Nav': 'Навигация и Модули',
    'Brd': 'Бренды и Партнеры',
    'Sts': 'Статусы и Уведомления',
    'Mrk': 'Маркеры и Иллюстрации',
    'Ill': 'Иллюстрации',
    'Acc': 'Акцентные карты и счета'
}

def parse_js_icon(fpath):
    with open(fpath, 'r', encoding='utf-8') as f:
        code = f.read()

    fname = os.path.basename(fpath)
    icon_name = fname[:-3] # remove .js

    svg_matches = re.findall(r'createElement\("svg",\s*(\{[^\}]+\}),\s*(.*?)\)\)\)', code, re.DOTALL)
    if not svg_matches:
        svg_matches = re.findall(r'createElement\("svg",\s*(\{[^\}]+\})\s*,(.*)', code, re.DOTALL)

    if not svg_matches:
        return None

    props_str, children_str = svg_matches[-1]

    width_m = re.search(r'width:\s*"([^"]+)"', props_str)
    height_m = re.search(r'height:\s*"([^"]+)"', props_str)
    viewbox_m = re.search(r'viewBox:\s*"([^"]+)"', props_str)

    w = width_m.group(1) if width_m else '24'
    h = height_m.group(1) if height_m else '24'
    vb = viewbox_m.group(1) if viewbox_m else f'0 0 {w} {h}'

    child_matches = re.findall(r'createElement\("([a-z]+)",\s*(\{.*?\}|\(\{\}\)\))\)?', children_str)

    elements_xml = []
    for match in child_matches:
        tag = match[0]
        cprops = match[1]
        attrs = []
        for k, v in re.findall(r'([a-zA-Z0-9]+):\s*"([^"]*)"', cprops):
            if k in ['className', 'table', 'data-test-id']:
                continue
            attr_name = k
            if k == 'fillRule': attr_name = 'fill-rule'
            elif k == 'clipRule': attr_name = 'clip-rule'
            elif k == 'strokeWidth': attr_name = 'stroke-width'
            elif k == 'strokeLinecap': attr_name = 'stroke-linecap'
            elif k == 'strokeLinejoin': attr_name = 'stroke-linejoin'
            attrs.append(f'{attr_name}="{v}"')

        if not any('fill=' in a for a in attrs):
            attrs.append('fill="currentColor"')

        attr_str = ' '.join(attrs)
        elements_xml.append(f'<{tag} {attr_str}/>')

    inner_xml = ''.join(elements_xml)
    svg_code = f'<svg width="{w}" height="{h}" viewBox="{vb}" fill="none" xmlns="http://www.w3.org/2000/svg">{inner_xml}</svg>'
    
    # Size extraction
    size_match = re.search(r'(\d+)$', icon_name)
    size_str = size_match.group(1) if size_match else w

    # Category extraction (Prd, Srv, Nav, Brd, Sts, Mrk, Ill, Acc)
    cat_match = re.search(r'(Prd|Prdx|Srv|Srvx|Nav|Brd|Sts|Mrk|Ill|Acc)', icon_name)
    cat_code = cat_match.group(1) if cat_match else 'Other'
    cat_title = CATEGORIES.get(cat_code, 'Общие иконки')

    # Meaning translation
    # Strip size and category suffix
    core_name = re.sub(r'(Prd|Prdx|Srv|Srvx|Nav|Brd|Sts|Mrk|Ill|Acc)?\d*$', '', icon_name).lower()
    
    meaning = TRANSLATIONS.get(core_name)
    if not meaning:
        # Fallback partial match search
        for key in sorted(TRANSLATIONS.keys(), key=lambda x: len(x), reverse=True):
            if key in core_name:
                meaning = TRANSLATIONS[key]
                break
    if not meaning:
        meaning = core_name.capitalize()

    return {
        'name': icon_name,
        'meaning': meaning,
        'category': cat_title,
        'cat_code': cat_code,
        'size': size_str,
        'svg': svg_code,
        'width': w,
        'height': h
    }

def main():
    icons_dir = r'd:\SameLightsalmonEmulators\SameLightsalmonEmulators\@sberbusiness\icons'
    js_files = sorted(glob.glob(os.path.join(icons_dir, '*.js')))
    
    icons_data = []
    print(f"Found {len(js_files)} JS icon files. Parsing...")
    
    for fpath in js_files:
        icon_info = parse_js_icon(fpath)
        if icon_info and icon_info['svg']:
            icons_data.append(icon_info)

    print(f"Successfully parsed {len(icons_data)} icons with human translations.")

    json_str = json.dumps(icons_data, ensure_ascii=False)

    html_content = f"""<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Справочник Иконок СберБизнес ({len(icons_data)} иконок с описанием)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {{
            --bg-page: #f8fafc;
            --bg-card: #ffffff;
            --bg-card-hover: #f1f5f9;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --primary-color: #22c55e;
            --primary-hover: #16a34a;
            --accent-blue: #2563eb;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --radius-card: 16px;
        }}

        [data-theme="dark"] {{
            --bg-page: #0f172a;
            --bg-card: #1e293b;
            --bg-card-hover: #334155;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.4);
        }}

        * {{
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }}

        body {{
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-main);
            padding: 32px 24px;
            transition: background-color 0.25s ease, color 0.25s ease;
            min-height: 100vh;
        }}

        .container {{
            max-width: 1440px;
            margin: 0 auto;
        }}

        .header {{
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 28px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border-color);
        }}

        .brand-title {{
            display: flex;
            align-items: center;
            gap: 12px;
        }}

        .brand-title h1 {{
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #22c55e, #10b981, #2563eb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }}

        .badge-count {{
            background: var(--accent-blue);
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
        }}

        .controls-row {{
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 28px;
        }}

        .search-box {{
            width: 100%;
            position: relative;
        }}

        .search-box input {{
            width: 100%;
            padding: 14px 20px 14px 44px;
            font-size: 15px;
            font-family: inherit;
            border-radius: 14px;
            border: 1.5px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-main);
            outline: none;
            box-shadow: var(--shadow-sm);
            transition: all 0.2s ease;
        }}

        .search-box input:focus {{
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15);
        }}

        .search-icon {{
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            fill: var(--text-muted);
            pointer-events: none;
        }}

        .filter-tags {{
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }}

        .filter-tag {{
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.15s ease;
        }}

        .filter-tag:hover, .filter-tag.active {{
            background: var(--primary-color);
            color: #ffffff;
            border-color: var(--primary-color);
        }}

        .theme-toggle-btn {{
            padding: 10px 18px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-main);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }}

        .theme-toggle-btn:hover {{
            background: var(--bg-card-hover);
        }}

        .icons-grid {{
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }}

        .icon-card {{
            background: var(--bg-card);
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-card);
            padding: 20px 16px 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            box-shadow: var(--shadow-sm);
            transition: all 0.2s ease;
            position: relative;
        }}

        .icon-card:hover {{
            transform: translateY(-3px);
            border-color: var(--primary-color);
            box-shadow: var(--shadow-md);
        }}

        .icon-preview {{
            width: 72px;
            height: 72px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-page);
            border-radius: 14px;
            margin-bottom: 12px;
            color: var(--text-main);
            transition: color 0.2s ease;
        }}

        .icon-preview svg {{
            width: auto;
            height: auto;
            max-width: 48px;
            max-height: 48px;
            fill: currentColor;
        }}

        .icon-meaning {{
            font-size: 14px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 4px;
            line-height: 1.3;
        }}

        .icon-name {{
            font-size: 12px;
            font-weight: 600;
            word-break: break-word;
            margin-bottom: 8px;
            color: var(--text-muted);
        }}

        .icon-meta-row {{
            display: flex;
            gap: 6px;
            margin-bottom: 14px;
        }}

        .icon-badge {{
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            background: var(--bg-page);
            padding: 2px 8px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }}

        .card-actions {{
            display: flex;
            gap: 8px;
            width: 100%;
            margin-top: auto;
        }}

        .copy-btn {{
            flex: 1;
            padding: 8px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--bg-page);
            color: var(--text-main);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
        }}

        .copy-btn:hover {{
            background: var(--primary-color);
            color: #ffffff;
            border-color: var(--primary-color);
        }}

        .toast {{
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #0f172a;
            color: #ffffff;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.25s ease;
            pointer-events: none;
            z-index: 1000;
        }}

        .toast.show {{
            opacity: 1;
            transform: translateY(0);
        }}
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="brand-title">
                <h1>SberBusiness Icons</h1>
                <span class="badge-count" id="countBadge">{len(icons_data)} иконок</span>
            </div>
            <button class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleTheme()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9 9-4.03 9-9c0-.46-.04-.92-.1-1.36-.98 1.37-2.58 2.26-4.4 2.26-2.98 0-5.4-2.42-5.4-5.4 0-1.81.89-3.42 2.26-4.4-.44-.06-.9-.1-1.36-.1z"/></svg>
                Переключить тему
            </button>
        </header>

        <div class="controls-row">
            <div class="search-box">
                <svg class="search-icon" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input type="text" id="searchInput" placeholder="Поиск по названию или значению (например: карта, чат, документ, сбережения, кредиты, настройки, 32px...)" oninput="filterIcons()">
            </div>
            <div class="filter-tags" id="catFilters">
                <button class="filter-tag active" data-cat="all" onclick="setCatFilter(this, 'all')">Все категории</button>
                <button class="filter-tag" data-cat="Prd" onclick="setCatFilter(this, 'Prd')">Продукты и Сервисы (Prd)</button>
                <button class="filter-tag" data-cat="Srv" onclick="setCatFilter(this, 'Srv')">Интерфейс и Кнопки (Srv)</button>
                <button class="filter-tag" data-cat="Nav" onclick="setCatFilter(this, 'Nav')">Навигация (Nav)</button>
                <button class="filter-tag" data-cat="Brd" onclick="setCatFilter(this, 'Brd')">Бренды (Brd)</button>
                <button class="filter-tag" data-cat="Sts" onclick="setCatFilter(this, 'Sts')">Статусы (Sts)</button>
            </div>
        </div>

        <div class="icons-grid" id="iconsGrid"></div>
    </div>

    <div class="toast" id="toast">Скопировано!</div>

    <script>
        const icons = {json_str};
        let activeCat = 'all';

        function renderIcons(data) {{
            const grid = document.getElementById('iconsGrid');
            grid.innerHTML = '';
            document.getElementById('countBadge').innerText = data.length + ' иконок';

            if (data.length === 0) {{
                grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 48px; color: var(--text-muted); font-size: 16px; font-weight: 600;">Иконки не найдены</div>';
                return;
            }}

            const fragment = document.createDocumentFragment();
            data.forEach(icon => {{
                const card = document.createElement('div');
                card.className = 'icon-card';
                card.innerHTML = `
                    <div class="icon-preview">${{icon.svg}}</div>
                    <div class="icon-meaning" title="${{icon.meaning}}">${{icon.meaning}}</div>
                    <div class="icon-name" title="${{icon.name}}">${{icon.name}}</div>
                    <div class="icon-meta-row">
                        <span class="icon-badge">${{icon.width}}x${{icon.height}}px</span>
                        <span class="icon-badge">${{icon.cat_code}}</span>
                    </div>
                    <div class="card-actions">
                        <button class="copy-btn" onclick="copySvg('${{icon.name}}')">SVG</button>
                        <button class="copy-btn" onclick="copyName('${{icon.name}}')">Имя</button>
                    </div>
                `;
                fragment.appendChild(card);
            }});
            grid.appendChild(fragment);
        }}

        function filterIcons() {{
            const query = document.getElementById('searchInput').value.toLowerCase().trim();
            const filtered = icons.filter(icon => {{
                const matchesName = icon.name.toLowerCase().includes(query);
                const matchesMeaning = icon.meaning.toLowerCase().includes(query);
                const matchesCatCode = icon.cat_code.toLowerCase().includes(query);
                const matchesSize = (icon.width + 'px').includes(query);

                const matchesSearch = matchesName || matchesMeaning || matchesCatCode || matchesSize;
                const matchesCategory = (activeCat === 'all') || (icon.cat_code.startsWith(activeCat));

                return matchesSearch && matchesCategory;
            }});
            renderIcons(filtered);
        }}

        function setCatFilter(btn, cat) {{
            document.querySelectorAll('.filter-tag').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activeCat = cat;
            filterIcons();
        }}

        function copySvg(iconName) {{
            const icon = icons.find(i => i.name === iconName);
            if (icon) {{
                navigator.clipboard.writeText(icon.svg).then(() => {{
                    showToast('SVG код иконки ' + iconName + ' скопирован!');
                }});
            }}
        }}

        function copyName(iconName) {{
            navigator.clipboard.writeText(iconName).then(() => {{
                showToast('Имя иконки ' + iconName + ' скопировано!');
            }});
        }}

        function showToast(msg) {{
            const t = document.getElementById('toast');
            t.innerText = msg;
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 2200);
        }}

        function toggleTheme() {{
            const body = document.body;
            if (body.getAttribute('data-theme') === 'dark') {{
                body.removeAttribute('data-theme');
            }} else {{
                body.setAttribute('data-theme', 'dark');
            }}
        }}

        // Initial render
        renderIcons(icons);
    </script>
</body>
</html>
"""

    out_paths = [
        r'd:\SameLightsalmonEmulators\SameLightsalmonEmulators\@sberbusiness\icons\index.html',
        r'd:\SameLightsalmonEmulators\SameLightsalmonEmulators\@sberbusiness\icons\index.php',
        r'd:\SameLightsalmonEmulators\SameLightsalmonEmulators\@sberbusiness\index.html',
        r'd:\SameLightsalmonEmulators\SameLightsalmonEmulators\@sberbusiness\index.php',
        r'd:\SameLightsalmonEmulators\SameLightsalmonEmulators\sberbusiness_icons.php'
    ]

    for p in out_paths:
        os.makedirs(os.path.dirname(p), exist_ok=True)
        with open(p, 'w', encoding='utf-8') as f:
            f.write(html_content)
        print(f"Generated gallery with human translations at: {p}")

if __name__ == '__main__':
    main()
