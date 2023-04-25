const config = {
  'baseurl': 'https://lucascranach.org/admin/browser.php',
  'templateDirItem': document.querySelector('#dirTemplate').innerHTML,
  'targetDirs': document.querySelector('#dirs'),
  'templateImage': document.querySelector('#imageTemplate').innerHTML,
  'targetStage': document.querySelector('#stageContent'),
  'targetStageId': 'stageContent',
  'templateSearchInfo': document.querySelector('#searchInfoTemplate').innerHTML,
  'targetSearchInfo': document.querySelector('#searchInfo'),
  'templateImageInfo': document.querySelector('#imageInfoTemplate').innerHTML,
  'targetImageInfo': document.querySelector('#imageInfo'),
  'imageurl': 'https://lucascranach.org/imageserver-2021/',
  'targetSearch': document.querySelector('#search'),
  'targetSearchField': document.querySelector('#searchField'),
  'timeoutId': false,
  'typemap': {
    'folder': 'folder_open',
    'back': 'chevron_left',
    'image': 'image',
    'zoomableImage': 'aspect_ratio',
    'dzi': 'aspect_ratio',
    'json': 'description',
    'error': 'error',
    'position': 'location_on',
    'size': 'crop_16_9',
    'iptc': 'list'
  },
  'stagemap': {
    'image': 'stage--centered',
    'zoomableImage': 'stage--centered',
    'json': 'stage--normal'
  }
};

class Helper {

  constructor() {
    this.support = this.checkSupport();
  }

  checkSupport() {
    if (!window.DOMParser) return false;
    var parser = new DOMParser();
    try {
      parser.parseFromString('x', 'text/html');
    } catch (err) {
      return false;
    }
    return true;
  }

  convertToDomElement(str) {
    if (this.support) {
      var parser = new DOMParser();
      var doc = parser.parseFromString(str, 'text/html');
      return doc.body;
    }

    var dom = document.createElement('div');
    dom.innerHTML = str;
    return dom;
  }

  createSlugFromString(str) {
    const slug = str.replace(/[^a-zA-Z0-9]/g, "");
    return slug;
  }
}

class FileBrowser {

  constructor() {
    this.config = config;
    this.path = "";
    this.activeStageContentType = false;
    this.viewer = false;
    this.jsonViewer = false;
    this.lastFolder = false;
    this.rootLevel = false;
    this.searchValue = false;
    this.initSearch();
  }

  clearContainer(target, pattern) {
    target.querySelectorAll(pattern).forEach(ele => {
      target.removeChild(ele);
    });
  }

  showFiles() {
    const data = this.activeData;
    const template = this.config.templateDirItem;
    const target = this.config.targetDirs;
    const targetSearch = this.config.targetSearch;
    const items = [...data.folder, ...data.images, ...data.jsons, ...data.dzi];

    this.clearContainer(target, "li");

    if (data.meta.level > 0) {
      const back = {
        "src": data.prevLevel,
        "name": "…",
        "type": "back"
      };
      items.unshift(back);
    }
    
    for (var ele in items) {
      const item = items[ele];
      item.icon = this.config.typemap[item.type];
      item.id = helper.createSlugFromString(item.src);
      const output = Mustache.render(template, item);
      const outputDom = helper.convertToDomElement(output);
      target.append(...outputDom.children);
    }

    target.querySelectorAll("li[data-type=folder], li[data-type=back]").forEach(ele => {
      ele.addEventListener('click', e => {
        const target = e.currentTarget;
        this.path = target.dataset.src;
        if (target.dataset.type === 'folder' && this.rootLevel) { this.lastFolder = target.id; }
        this.showData("files");
      });
    });

    target.querySelectorAll("li[data-type=image]").forEach(ele => {
      ele.addEventListener('click', ele => {
        const target = ele.currentTarget;
        this.showImage(target.dataset.src);
      });
    });

    target.querySelectorAll("li[data-type=zoomableImage]").forEach(ele => {
      ele.addEventListener('click', e => {
        const target = e.currentTarget;
        this.showZoomableImage(target.dataset.src);
      });
    });

    target.querySelectorAll("li[data-type=dzi]").forEach(ele => {
      ele.addEventListener('click', e => {
        const target = e.currentTarget;
        this.showZoomableImage(target.dataset.src);
      });
    });

    target.querySelectorAll("li[data-type=json]").forEach(ele => {
      ele.addEventListener('click', e => {
        const target = e.currentTarget;
        this.showJson(target.dataset.src);
      });
    });

    if (!this.rootLevel) {
      targetSearch.classList.add('search--is-hidden');
    } else {
      this.focusCurrentItem();
      targetSearch.classList.remove('search--is-hidden');
    }
    
  }
  
  initSearch() {
    const target = this.config.targetSearchField;
    target.addEventListener('keyup', (e) => {
      try{window.clearTimeout(this.timeoutId);}catch(e){}
      this.timeoutId = setTimeout(this.search.bind(this), 500);
    });
  }

  updateResults(data, size) {
    this.activeData.folder = [...data];
    this.activeData.meta.folder = size;
    this.showSearchInfo();
    this.showFiles();
  }
  
  search() {
    const target = this.config.targetSearchField;
    
    this.searchValue = target.value;
    if (this.searchValue.length === 0) {
      this.updateResults(this.data.folder, this.data.folder.length);
    };

    const searchableData = [...this.data.folder];
    
    const pattern = new RegExp(`${this.searchValue}`, 'i');
    const results = searchableData.filter(item => {
      return item.name.match(pattern);
    });
    
    this.updateResults(results, results.length);
  }

  focusCurrentItem() {
    
    if (!this.lastFolder) return;

    const target = document.getElementById(this.lastFolder);
    if (!target) return;
    
    target.scrollIntoView({
      behavior: 'smooth'
    });

    target.classList.add("dir-list__item--is-active");
  }

  setStage(type) {
    const cssClass = this.config.stagemap[type];
    const target = this.config.targetStage;
    target.className = '';
    target.classList.add(cssClass);
  }

  showImage(src) {
    const template = this.config.templateImage;
    const target = this.config.targetStage;
    const type = 'image';
    const url = `${this.config.imageurl}${src}`;
    const data = {
      'src': url
    };
    
    this.clearStageContainer();
    this.setStage(type);

    const output = Mustache.render(template, data);
    const outputDom = helper.convertToDomElement(output);
    target.append(...outputDom.children);

    this.activeStageContentType = type;
    this.getIPTC(src);
  }


  showIPTC(meta) {
    let displayData = [];
    const target = this.config.targetImageInfo;
    const template = this.config.templateImageInfo;

    this.clearContainer(target, 'li');
    
    if (meta.size) {
      displayData.push({ 'text': `Größe: ${meta.size['0']}x${meta.size['0']}`, 'type': 'size' });
    }

    const iptcData = meta.iptc ? meta.iptc : '-';
    displayData.push({ 'text': `IPTC: ${iptcData}`, 'type': 'iptc' });

    for (var ele in displayData) {
      const item = displayData[ele];
      item.icon = this.config.typemap[item.type];
      const output = Mustache.render(template, item);
      const outputDom = helper.convertToDomElement(output);
      target.append(...outputDom.children);
    }

  }

  getIPTC(url) {
    const imgUrl = this.getUrl(url);

    (async () => {
      let response = await fetch(imgUrl);
      let data = await response.json();
      this.showIPTC(data);
    })();

  }

  showSearchInfo() {
    const data = this.activeData;
    const target = this.config.targetSearchInfo;
    const template = this.config.templateSearchInfo;
    const displayData = [];

    this.clearContainer(target, 'li');

    if (data.meta.folder > 0) {
      const plural = data.meta.folder == 1 ? "" : "e";
      displayData.push({ 'text': `${data.meta.folder} Verzeichniss${plural} gefunden`, 'type': 'folder' });
    }
    if (data.meta.images > 0) {
      const plural = data.meta.images == 1 ? "" : "er";
      displayData.push({ 'text': `${data.meta.images} Bild${plural} gefunden`, 'type': 'image' });
    }
    
    if (this.path !== '') {  
      displayData.push({ 'text': this.path, 'type': 'position', 'class': 'important' });
    }

    for (var ele in displayData) {
      const item = displayData[ele];
      item.icon = this.config.typemap[item.type];
      const output = Mustache.render(template, item);
      const outputDom = helper.convertToDomElement(output);
      target.append(...outputDom.children);
    }
  }

  clearStageContainer() {

    if (this.activeStageContentType === 'image') {
      const target = this.config.targetStage;
      this.clearContainer(target, "img");
    }if (this.activeStageContentType === 'json') {
      const target = this.config.targetStage;
      this.clearContainer(target, "pre");
    } else {
      if(this.viewer)this.viewer.destroy();
    }

  }

  showZoomableImage(src) {

    const targetStageId = this.config.targetStageId;
    const url = `${this.config.imageurl}/${src}`;
    const type = 'zoomableImage';
    let imgData = false;
    
    this.clearStageContainer();
    this.setStage(type);

    if (src.match(/\.dzi$/)) {
      imgData = url;
    } else {
      imgData = {
        type: 'image',
        url: url
      }
    }

    this.viewer = OpenSeadragon({
      id: targetStageId,
      prefixUrl: "./assets/icons/",
      tileSources: imgData
    });

    this.activeStageContentType = type;

    
    const metaSrc = src.replace(/\.dzi$/, "-origin.jpg");
    this.getIPTC(metaSrc);
  }

  showJson(src) {
    const target = this.config.targetStage;
    const url = `${this.config.imageurl}/${src}`;
    const type = 'json';

    this.clearStageContainer();
    this.setStage(type);

    this.jsonViewer = new JSONViewer();
    target.appendChild(this.jsonViewer.getContainer());

    (async () => {
      let response = await fetch(url);
      let data = await response.json();
      this.jsonViewer.showJSON(data, 20, 3);
    })();

    this.activeStageContentType = type;
  }

  getUrl(segment) {
    const baseurl = this.config.baseurl;
    const path = segment ? segment : this.path;
    return (path !== "") ? `${baseurl}?path=${path}` : baseurl
  }

  showData() {

    (async () => {
      const url = this.getUrl();
      let response = await fetch(url);
      let data = await response.json();

      this.activeData = { ...data };
      this.data = { ...data };

      this.rootLevel = (this.path === '') ? true : false;
      if (this.searchValue && this.rootLevel) this.search();
      this.showFiles();
      this.showSearchInfo();
      // console.log(this);
    })();

  }
}

var helper = new Helper();
var fileBrowser = new FileBrowser();
fileBrowser.showData();
