// External Dependencies
import { Component } from 'react';
import React from 'react';

// Internal Dependencies
import './style.css';


class REACG_DiviModule extends Component {

  static slug = 'reacg_module';

  componentDidUpdate() {
    const galleries = document.getElementsByClassName("reacg-gallery");
    if ( galleries && galleries.length > 0 ) {
      Array.from(galleries).forEach((gallery) => {
        // Check if the gallery container is empty.
        if ( gallery.innerHTML.trim() === "" ) {
          const button = document.getElementById("reacg-loadApp");
          if ( button ) {
            button.setAttribute("data-id", "reacg-root" + this.props.post_id);
            button.click();
          }
        }
      });
    }
  }

  render() {
    return (<div className= {'reacg-wrapper reacg-gallery reacg-preview'}
                 key={this.props.post_id + this.props.enable_options}
                 data-options-section={this.props.enable_options === "on" ? 1 : 0}
                 data-plugin-version={Date.now()}
                 data-gallery-timestamp={Date.now()}
                 data-options-timestamp={Date.now()}
                 data-gallery-id={this.props.post_id}
                 id={"reacg-root" + this.props.post_id}
                ></div>);

  }
}

export default REACG_DiviModule;
