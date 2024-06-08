// External Dependencies
import { Component } from 'react';
import React from 'react';

// Internal Dependencies
import './style.css';


class REACG_DiviModule extends Component {

  static slug = 'reacg_module';

  componentDidUpdate() {
    if ( document.getElementsByClassName("reacg-gallery").length > 0
      && document.getElementsByClassName("reacg-gallery")[0].getInnerHTML() === '' ) {
      let options = this.props.enable_options;
      document.getElementById('reacg-loadApp').setAttribute('data-id', 'reacg-root' + this.props.post_id);
      let button = document.querySelectorAll('#reacg-loadApp');
      if ( button.length > 1 ) {
        button[button.length - 1].click();
      }
      else {
        button.click();
      }
    }
  }

  render() {
    return (<div className= {'reacg-gallery reacg-preview'}
                 key={this.props.post_id + this.props.enable_options}
                 data-options-section={this.props.enable_options === "on" ? 1 : 0}
                 data-gallery-id= {this.props.post_id}
                 id={"reacg-root" + this.props.post_id}
                ></div>);

  }
}

export default REACG_DiviModule;
