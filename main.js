const config = {
  'baseurl': 'https://lucascranach.org/admin/browser.php'
};

var Mustache = require('mustache');

class FileBrowser {
  
  constructor() {
    this.config = config;
  }

  showFiles(folder) {

    for (var item in folder) {
      console.log(folder[item]);
    }

  }

  showData(type) {
    
    const baseurl = this.config.baseurl;
    (async () => {
      let response = await fetch(baseurl);
      let data = await response.json();

      switch (type) {
        case "files":
          this.showFiles(data.folder);
          break;
        default:
          console.log(data);
      }
    })();

  }

}

var fileBrowser = new FileBrowser();
fileBrowser.showData("files");